#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
SDK_DIR="${1:-$ROOT_DIR/clients/java}"

if [ ! -f "$SDK_DIR/pom.xml" ]; then
  echo "ERROR: generated Java SDK not found at $SDK_DIR. Run: make generate-java" >&2
  exit 1
fi

mvn -f "$SDK_DIR/pom.xml" clean package -DskipTests

VERSION=$(mvn -q -f "$SDK_DIR/pom.xml" help:evaluate \
  -Dexpression=project.version -DforceStdout)
TARGET_DIR="$SDK_DIR/target"
FAT_JAR="$TARGET_DIR/remap-1.2-java-sdk-$VERSION.jar"
SLIM_JAR="$TARGET_DIR/remap-1.2-java-sdk-$VERSION-slim.jar"

for artifact in "$FAT_JAR" "$SLIM_JAR"; do
  if [ ! -f "$artifact" ]; then
    echo "ERROR: expected artifact not found: $artifact" >&2
    exit 1
  fi
done

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM
jar tf "$FAT_JAR" > "$TMP_DIR/fat-entries"
jar tf "$SLIM_JAR" > "$TMP_DIR/slim-entries"

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
  assert_no_prefix "$prefix" "$TMP_DIR/fat-entries" "fat JAR"
  assert_no_prefix "$prefix" "$TMP_DIR/slim-entries" "slim JAR"
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
  assert_has_prefix "$prefix" "$TMP_DIR/fat-entries" "fat JAR"
  assert_no_prefix "$prefix" "$TMP_DIR/slim-entries" "slim JAR"
done

assert_has_prefix "ru/moysklad/remap_1_2/ApiClient.class" "$TMP_DIR/fat-entries" "fat JAR"
assert_has_prefix "ru/moysklad/remap_1_2/ApiClient.class" "$TMP_DIR/slim-entries" "slim JAR"

if ! awk '
  /<dependencies>/ { in_dependencies = 1; next }
  in_dependencies && /<\/dependencies>/ { in_dependencies = 0; exit }
  in_dependencies && /<dependency>/ { dependencies++ }
  in_dependencies && /<optional>true<\/optional>/ { optional++ }
  END { exit(dependencies > 0 && dependencies == optional ? 0 : 1) }
' "$SDK_DIR/pom.xml"; then
  echo "ERROR: every SDK dependency must be optional for consumers" >&2
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

javac -cp "$FAT_JAR" -d "$TMP_DIR/classes" "$TMP_DIR/FatConsumer.java"
java -cp "$TMP_DIR/classes:$FAT_JAR" FatConsumer

echo "Java SDK packaging verification passed"
