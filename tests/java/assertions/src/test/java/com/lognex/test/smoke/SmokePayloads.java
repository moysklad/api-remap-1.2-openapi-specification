package com.lognex.test.smoke;

final class SmokePayloads {
    private SmokePayloads() {
    }

    static String resolve(SmokeEndpointCase testCase) {
        if (!testCase.hasBody()) {
            return null;
        }

        String method = testCase.getMethod();
        String path = testCase.getPath();

        if (("POST".equals(method) || "PUT".equals(method)) && path.endsWith("/delete")) {
            return "[]";
        }

        if ("POST".equals(method) && path.endsWith("/files")) {
            return "[{\"filename\":\"doc.pdf\",\"content\":\"SGVsbG8gV29ybGQ=\"}]";
        }

        if ("POST".equals(method) && path.endsWith("/images")) {
            return "{\"filename\":\"test.png\",\"content\":\"iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADklEQVQI12P4z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==\"}";
        }

        if ("POST".equals(method) && "/entity/move".equals(path)) {
            return "{\"sourceStore\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/store/12345678-1234-1234-1234-123456789012\",\"type\":\"store\",\"mediaType\":\"application/json\"}},\"targetStore\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/store/12345678-1234-1234-1234-123456789012\",\"type\":\"store\",\"mediaType\":\"application/json\"}}}";
        }

        if ("POST".equals(method) && "/entity/loss".equals(path)) {
            return "{\"organization\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/organization/12345678-1234-1234-1234-123456789012\",\"type\":\"organization\",\"mediaType\":\"application/json\"}},\"store\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/store/12345678-1234-1234-1234-123456789012\",\"type\":\"store\",\"mediaType\":\"application/json\"}}}";
        }

        if ("POST".equals(method) && ("/entity/salesreturn".equals(path) || "/entity/supply".equals(path) || "/entity/purchasereturn".equals(path) || "/entity/enter".equals(path))) {
            return "{\"organization\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/organization/12345678-1234-1234-1234-123456789012\",\"type\":\"organization\",\"mediaType\":\"application/json\"}},\"agent\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/counterparty/12345678-1234-1234-1234-123456789012\",\"type\":\"counterparty\",\"mediaType\":\"application/json\"}},\"store\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/store/12345678-1234-1234-1234-123456789012\",\"type\":\"store\",\"mediaType\":\"application/json\"}}}";
        }

        if ("POST".equals(method) && path.endsWith("/metadata/states")) {
            return "{\"name\":\"state1\",\"color\":15106326,\"stateType\":\"Regular\"}";
        }

        if (("POST".equals(method) || "PUT".equals(method)) && path.contains("/metadata/attributes")) {
            return "{\"name\":\"attr1\"}";
        }

        if ("POST".equals(method) && path.endsWith("/positions/batch")) {
            return "[{\"quantity\":1}]";
        }

        if (("POST".equals(method) || "PUT".equals(method)) && path.contains("/positions")) {
            return "{\"quantity\":1}";
        }

        if ("POST".equals(method) && path.contains("/metadata/characteristics")) {
            return "{\"name\":\"Characteristic X\"}";
        }

        if ("PUT".equals(method) && path.endsWith("/access/activate")) {
            return "{\"login\":\"newemployee@lognex\"}";
        }

        if ("POST".equals(method) && path.endsWith("/export")) {
            return "{\"organization\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/organization/12345678-1234-1234-1234-123456789012\",\"type\":\"organization\",\"mediaType\":\"application/json\"}},\"count\":10,\"salePrice\":{\"priceType\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/context/companysettings/pricetype/12345678-1234-1234-1234-123456789012\",\"type\":\"pricetype\",\"mediaType\":\"application/json\"}}},\"template\":{\"meta\":{\"href\":\"https://api.moysklad.ru/api/remap/1.2/entity/assortment/metadata/embeddedtemplate/12345678-1234-1234-1234-123456789012\",\"type\":\"embeddedtemplate\",\"mediaType\":\"application/json\"}}}";
        }

        return "{\"name\":\"Smoke Test\"}";
    }
}
