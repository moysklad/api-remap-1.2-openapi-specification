#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
SDK_DIR="${1:-$ROOT_DIR/clients/java}"

if [ ! -f "$SDK_DIR/pom.xml" ]; then
  echo "ERROR: generated Java SDK not found at $SDK_DIR. Run: make generate-java" >&2
  exit 1
fi

if grep -n '<classifier>slim</classifier>' "$SDK_DIR/pom.xml" >/dev/null 2>&1; then
  echo "ERROR: classifier-based slim packaging is not allowed" >&2
  exit 1
fi

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM

VERSION=$(mvn -N -q -f "$SDK_DIR/pom.xml" help:evaluate \
  -Dexpression=project.version -DforceStdout)
MAVEN_JAVA_HOME=$(mvn -N -q -f "$SDK_DIR/pom.xml" help:evaluate \
  -Dexpression=java.home -DforceStdout)
JAVAC_CMD="javac"
JAVA_CMD="java"
if [ -x "$MAVEN_JAVA_HOME/bin/javac" ] && [ -x "$MAVEN_JAVA_HOME/bin/java" ]; then
  JAVAC_CMD="$MAVEN_JAVA_HOME/bin/javac"
  JAVA_CMD="$MAVEN_JAVA_HOME/bin/java"
fi

TARGET_JAR="$SDK_DIR/target/remap-1.2-java-sdk-$VERSION.jar"
SLIM_JAR="$TMP_DIR/remap-1.2-java-sdk-$VERSION-slim.jar"
FAT_JAR="$TMP_DIR/remap-1.2-java-sdk-$VERSION-fat.jar"
CLASSIFIER_SLIM_JAR="$SDK_DIR/target/remap-1.2-java-sdk-$VERSION-slim.jar"

mvn -f "$SDK_DIR/pom.xml" clean package -DskipTests
cp "$TARGET_JAR" "$SLIM_JAR"

mvn -f "$SDK_DIR/pom.xml" -Pfat clean package -DskipTests
cp "$TARGET_JAR" "$FAT_JAR"

if [ -f "$CLASSIFIER_SLIM_JAR" ]; then
  echo "ERROR: unexpected classifier slim JAR: $CLASSIFIER_SLIM_JAR" >&2
  exit 1
fi

for artifact in "$SLIM_JAR" "$FAT_JAR"; do
  if [ ! -f "$artifact" ]; then
    echo "ERROR: expected artifact not found: $artifact" >&2
    exit 1
  fi
done

jar tf "$SLIM_JAR" > "$TMP_DIR/slim-entries"
jar tf "$FAT_JAR" > "$TMP_DIR/fat-entries"

has_prefix() {
  awk -v prefix="$1" '
    index($0, prefix) == 1 { found = 1; exit }
    END { exit(found ? 0 : 1) }
  ' "$2"
}

assert_has_prefix() {
  if ! has_prefix "$1" "$2"; then
    echo "ERROR: $3 does not contain expected prefix: $1" >&2
    exit 1
  fi
}

assert_no_prefix() {
  if has_prefix "$1" "$2"; then
    echo "ERROR: $3 unexpectedly contains prefix: $1" >&2
    exit 1
  fi
}

DEPENDENCY_PREFIXES="
tools/jackson/
com/fasterxml/
org/openapitools/jackson/nullable/
org/apache/hc/
org/slf4j/
javax/annotation/
"

for prefix in $DEPENDENCY_PREFIXES; do
  assert_no_prefix "$prefix" "$TMP_DIR/slim-entries" "slim JAR"
  assert_no_prefix "$prefix" "$TMP_DIR/fat-entries" "fat JAR"
done

assert_no_prefix "META-INF/versions/" "$TMP_DIR/fat-entries" "fat JAR"

RELOCATED_PREFIXES="
ru/moysklad/api/shaded/tools/jackson/
ru/moysklad/api/shaded/com/fasterxml/
ru/moysklad/api/shaded/org/openapitools/jackson/nullable/
ru/moysklad/api/shaded/org/apache/hc/
ru/moysklad/api/shaded/org/slf4j/
ru/moysklad/api/shaded/javax/annotation/
"

for prefix in $RELOCATED_PREFIXES; do
  assert_no_prefix "$prefix" "$TMP_DIR/slim-entries" "slim JAR"
  assert_has_prefix "$prefix" "$TMP_DIR/fat-entries" "fat JAR"
done

assert_has_prefix "ru/moysklad/remap_1_2/ApiClient.class" "$TMP_DIR/slim-entries" "slim JAR"
assert_has_prefix "ru/moysklad/remap_1_2/ApiClient.class" "$TMP_DIR/fat-entries" "fat JAR"

if grep -n '<optional>true</optional>' "$SDK_DIR/pom.xml" >/dev/null 2>&1; then
  echo "ERROR: default slim POM must expose SDK dependencies transitively" >&2
  exit 1
fi

mkdir -p "$TMP_DIR/fat-contents"
(
  cd "$TMP_DIR/fat-contents"
  jar xf "$FAT_JAR"
)

SERVICES_DIR="$TMP_DIR/fat-contents/META-INF/services"
if [ ! -d "$SERVICES_DIR" ]; then
  echo "ERROR: fat JAR does not contain transformed service descriptors" >&2
  exit 1
fi

service_count=0
for service in "$SERVICES_DIR"/*; do
  [ -f "$service" ] || continue
  service_count=$((service_count + 1))
  service_name=${service##*/}
  case "$service_name" in
    tools.jackson.*|com.fasterxml.*|org.openapitools.*|org.apache.*|org.slf4j.*|javax.annotation.*)
      echo "ERROR: service descriptor was not relocated: $service_name" >&2
      exit 1
      ;;
  esac
  if awk '
    /^(tools\.jackson|com\.fasterxml|org\.openapitools|org\.apache|org\.slf4j|javax\.annotation)\./ {
      found = 1
    }
    END { exit(found ? 0 : 1) }
  ' "$service"; then
    echo "ERROR: service provider was not relocated in $service_name" >&2
    exit 1
  fi
done

if [ "$service_count" -eq 0 ]; then
  echo "ERROR: fat JAR contains no service descriptors" >&2
  exit 1
fi

cat > "$TMP_DIR/FatConsumer.java" <<'EOF'
import ru.moysklad.remap_1_2.ApiClient;

public final class FatConsumer {
  public static void main(String[] args) {
    ApiClient client = new ApiClient();
    if (client.getObjectMapper() == null || client.getHttpClient() == null) {
      throw new IllegalStateException("ApiClient dependencies were not initialized");
    }
  }
}
EOF

"$JAVAC_CMD" -cp "$FAT_JAR" -d "$TMP_DIR/classes" "$TMP_DIR/FatConsumer.java"
"$JAVA_CMD" -cp "$TMP_DIR/classes:$FAT_JAR" FatConsumer

mvn -q -f "$SDK_DIR/pom.xml" dependency:build-classpath \
  -DincludeScope=runtime \
  -Dmdep.outputFile="$TMP_DIR/slim-classpath"

cat > "$TMP_DIR/SlimConsumer.java" <<'EOF'
import ru.moysklad.remap_1_2.ApiClient;

public final class SlimConsumer {
  public static void main(String[] args) {
    ApiClient client = new ApiClient();
    if (client.getObjectMapper() == null || client.getHttpClient() == null) {
      throw new IllegalStateException("ApiClient dependencies were not initialized");
    }
  }
}
EOF

SLIM_RUNTIME_CP="$SLIM_JAR:$(cat "$TMP_DIR/slim-classpath")"
"$JAVAC_CMD" -cp "$SLIM_RUNTIME_CP" -d "$TMP_DIR/classes" "$TMP_DIR/SlimConsumer.java"
"$JAVA_CMD" -cp "$TMP_DIR/classes:$SLIM_RUNTIME_CP" SlimConsumer

DEPLOY_SDK_DIR="$TMP_DIR/sdk-deploy"
cp -R "$SDK_DIR" "$DEPLOY_SDK_DIR"
DEPLOY_REPO="$TMP_DIR/deploy-repo"
MAVEN_LOCAL_REPO="$TMP_DIR/m2/repository"
mkdir -p "$DEPLOY_REPO"
if [ -d "$HOME/.m2/repository" ]; then
  mkdir -p "$TMP_DIR/m2"
  cp -R "$HOME/.m2/repository" "$MAVEN_LOCAL_REPO"
fi

mvn -q -f "$DEPLOY_SDK_DIR/pom.xml" versions:set \
  -DnewVersion="$VERSION" \
  -DgenerateBackupPoms=false \
  -Dmaven.repo.local="$MAVEN_LOCAL_REPO"
mvn -q -f "$DEPLOY_SDK_DIR/pom.xml" -Ppublishing-artifactory clean deploy -DskipTests \
  -Dmaven.repo.local="$MAVEN_LOCAL_REPO" \
  -DaltDeploymentRepository="verify::default::file://$DEPLOY_REPO" \
  -DartifactoryRepo=file://$DEPLOY_REPO

FAT_VERSION="$VERSION-fat"
mvn -q -f "$DEPLOY_SDK_DIR/pom.xml" versions:set \
  -DnewVersion="$FAT_VERSION" \
  -DgenerateBackupPoms=false \
  -Dmaven.repo.local="$MAVEN_LOCAL_REPO"
mvn -q -f "$DEPLOY_SDK_DIR/pom.xml" -Pfat,publishing-artifactory clean deploy -DskipTests \
  -Dmaven.repo.local="$MAVEN_LOCAL_REPO" \
  -DaltDeploymentRepository="verify::default::file://$DEPLOY_REPO" \
  -DartifactoryRepo=file://$DEPLOY_REPO

SLIM_POM="$DEPLOY_REPO/ru/moysklad/api/remap-1.2-java-sdk/$VERSION/remap-1.2-java-sdk-$VERSION.pom"
FAT_POM="$DEPLOY_REPO/ru/moysklad/api/remap-1.2-java-sdk/$FAT_VERSION/remap-1.2-java-sdk-$FAT_VERSION.pom"

for deployed_pom in "$SLIM_POM" "$FAT_POM"; do
  if [ ! -f "$deployed_pom" ]; then
    echo "ERROR: expected deployed POM not found: $deployed_pom" >&2
    exit 1
  fi
done

for dependency in jackson-core jackson-databind jackson-annotations jackson-databind-nullable httpclient5; do
  if ! grep -q "<artifactId>$dependency</artifactId>" "$SLIM_POM"; then
    echo "ERROR: slim POM must expose SDK dependency: $dependency" >&2
    exit 1
  fi
  if grep -q "<artifactId>$dependency</artifactId>" "$FAT_POM"; then
    echo "ERROR: fat POM must not export fat dependency: $dependency" >&2
    exit 1
  fi
done

echo "Java SDK fat/slim verification passed"
