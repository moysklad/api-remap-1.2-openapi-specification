#!/usr/bin/env python3
"""
MCP сервер для работы с OpenAPI спецификацией МойСклад API.
Предоставляет инструменты для получения информации о методах API.
"""
import asyncio
import json
import sys
import yaml
from pathlib import Path
from typing import Any, Dict, List

from mcp.server import Server
from mcp.server.stdio import stdio_server
from mcp.types import Tool, TextContent

from openapi_parser import OpenAPIParser


# Инициализация парсера
SPEC_PATH = Path(__file__).parent.parent / "src" / "openapi.yaml"
parser = OpenAPIParser(str(SPEC_PATH))
spec = parser.load()


def format_parameter(param: Dict[str, Any]) -> str:
    """Форматировать параметр для вывода."""
    param_type = param.get('schema', {}).get('type', 'string')
    required = param.get('required', False)
    description = param.get('description', '')
    
    result = f"- **{param.get('name')}** ({param_type})"
    if required:
        result += " **[обязательный]**"
    if description:
        result += f": {description}"
    
    return result


def format_schema_fields(schema: Dict[str, Any], indent: int = 0) -> str:
    """Рекурсивно форматировать поля схемы."""
    prefix = "  " * indent
    result = []
    
    properties = schema.get('properties', {})
    required = schema.get('required', [])
    
    for field_name, field_info in properties.items():
        if not isinstance(field_info, dict):
            continue
        
        field_type = field_info.get('type', 'object')
        description = field_info.get('description', '')
        is_required = field_name in required
        read_only = field_info.get('readOnly', False)
        example = field_info.get('example')
        enum = field_info.get('enum')
        nullable = field_info.get('nullable', False)
        deprecated = field_info.get('deprecated', False)
        
        field_str = f"{prefix}- **{field_name}** ({field_type})"
        
        if is_required:
            field_str += " **[обязательное]**"
        if read_only:
            field_str += " **[только чтение]**"
        if nullable:
            field_str += " **[nullable]**"
        if deprecated:
            field_str += " **[устаревшее]**"
        
        if description:
            field_str += f": {description}"
        
        if enum:
            field_str += f"\n{prefix}  Допустимые значения: {', '.join(map(str, enum))}"
        
        if example is not None:
            field_str += f"\n{prefix}  Пример: `{example}`"
        
        # Рекурсивно обрабатываем вложенные объекты
        if field_type == 'object' and 'properties' in field_info:
            field_str += "\n" + format_schema_fields(field_info, indent + 1)
        elif field_type == 'array' and 'items' in field_info:
            items = field_info['items']
            if isinstance(items, dict) and items.get('type') == 'object':
                field_str += "\n" + format_schema_fields(items, indent + 1)
        
        result.append(field_str)
    
    return "\n".join(result)


def format_response(response_code: str, response_info: Dict[str, Any]) -> str:
    """Форматировать информацию об ответе."""
    description = response_info.get('description', '')
    content = response_info.get('content', {})
    
    # Определяем, это ошибка или успешный ответ
    is_error = response_code.startswith('4') or response_code.startswith('5')
    
    result = f"### {response_code} {description}\n\n"
    
    if is_error:
        result += "**Это код ошибки**\n\n"
    
    for content_type, content_info in content.items():
        schema = content_info.get('schema', {})
        schema_ref = schema.get('$ref', '')
        
        result += f"**Тип контента:** {content_type}\n\n"
        
        if schema_ref:
            # Извлекаем путь к схеме из ссылки
            if '#' in schema_ref:
                # Относительный путь к файлу
                file_part = schema_ref.split('#')[0]
                pointer_part = schema_ref.split('#')[1] if '#' in schema_ref else ''
                
                # Если это ссылка на error.yaml
                if 'error.yaml' in file_part:
                    # Загружаем схему ошибки напрямую из файла
                    try:
                        error_file_path = Path(SPEC_PATH.parent / 'components' / 'schemas' / 'common' / 'error.yaml')
                        if error_file_path.exists():
                            with open(error_file_path, 'r', encoding='utf-8') as f:
                                error_schema = yaml.safe_load(f)
                            result += "**Структура ошибки:**\n\n"
                            result += format_schema_fields(error_schema)
                            result += "\n\n"
                        else:
                            result += f"**Схема:** Error (детали ошибки)\n\n"
                    except Exception as e:
                        result += f"**Схема:** Error (детали ошибки)\n\n"
                elif pointer_part.endswith('/error'):
                    # Попытка найти Error в компонентах
                    error_schema = spec.get('components', {}).get('schemas', {}).get('Error', {})
                    if error_schema:
                        result += "**Структура ошибки:**\n\n"
                        result += format_schema_fields(error_schema)
                        result += "\n\n"
                    else:
                        result += f"**Схема:** Error (детали ошибки)\n\n"
                else:
                    # Другая схема
                    if pointer_part:
                        schema_path = pointer_part.lstrip('#/').split('/')
                        if len(schema_path) >= 3 and schema_path[0] == 'components' and schema_path[1] == 'schemas':
                            schema_name = schema_path[2]
                            schema_info = parser.get_schema_info(schema_name)
                            if schema_info:
                                result += f"**Схема:** {schema_name}\n\n"
                                if schema_info.get('description'):
                                    result += f"{schema_info['description']}\n\n"
                                result += "**Поля ответа:**\n\n"
                                result += format_schema_fields(spec.get('components', {}).get('schemas', {}).get(schema_name, {}))
                                result += "\n\n"
                            else:
                                result += f"**Схема:** {schema_name}\n\n"
        elif schema:
            if 'type' in schema:
                result += f"**Тип:** {schema['type']}\n\n"
            if 'properties' in schema:
                result += "**Поля ответа:**\n\n"
                result += format_schema_fields(schema)
                result += "\n\n"
    
    return result


async def handle_get_endpoint_info(arguments: Dict[str, Any]) -> List[TextContent]:
    """Обработчик инструмента get_endpoint_info."""
    path = arguments.get('path')
    method = arguments.get('method')
    
    if not path or not method:
        return [TextContent(
            type="text",
            text="Ошибка: необходимо указать path и method"
        )]
    
    endpoint_info = parser.get_endpoint_info(path, method)
    
    if not endpoint_info:
        return [TextContent(
            type="text",
            text=f"Эндпоинт {method} {path} не найден в спецификации."
        )]
    
    # Формируем детальный ответ
    result = f"# Информация об эндпоинте\n\n"
    result += f"**Путь:** `{endpoint_info['path']}`\n"
    result += f"**Метод:** `{endpoint_info['method']}`\n\n"
    
    if endpoint_info.get('summary'):
        result += f"## {endpoint_info['summary']}\n\n"
    
    if endpoint_info.get('description'):
        result += f"{endpoint_info['description']}\n\n"
    
    if endpoint_info.get('tags'):
        result += f"**Теги:** {', '.join(endpoint_info['tags'])}\n\n"
    
    # Параметры
    parameters = endpoint_info.get('parameters', [])
    if parameters:
        result += "## Параметры запроса\n\n"
        for param in parameters:
            result += format_parameter(param) + "\n"
        result += "\n"
    
    # Тело запроса
    request_body = endpoint_info.get('requestBody')
    if request_body:
        result += "## Тело запроса\n\n"
        required = request_body.get('required', False)
        if required:
            result += "**[обязательное]**\n\n"
        
        content = request_body.get('content', {})
        for content_type, content_info in content.items():
            result += f"**Тип контента:** {content_type}\n\n"
            schema = content_info.get('schema', {})
            
            # Если есть $ref, получаем схему
            if '$ref' in schema:
                ref = schema['$ref']
                if '#' in ref:
                    schema_path = ref.split('#/')[-1].split('/')
                    if len(schema_path) >= 3 and schema_path[0] == 'components' and schema_path[1] == 'schemas':
                        schema_name = schema_path[2]
                        schema_info = parser.get_schema_info(schema_name)
                        if schema_info:
                            result += f"### Схема: {schema_name}\n\n"
                            if schema_info.get('description'):
                                result += f"{schema_info['description']}\n\n"
                            result += "### Поля:\n\n"
                            result += format_schema_fields(spec.get('components', {}).get('schemas', {}).get(schema_name, {}))
                            result += "\n\n"
            elif schema:
                result += format_schema_fields(schema)
                result += "\n\n"
    
    # Ответы
    responses = endpoint_info.get('responses', {})
    if responses:
        result += "## Возможные ответы\n\n"
        
        # Разделяем успешные ответы и ошибки
        success_codes = [code for code in responses.keys() if code.startswith('2')]
        error_codes = [code for code in responses.keys() if code.startswith('4') or code.startswith('5')]
        
        if success_codes:
            result += "### Успешные ответы\n\n"
            for code in sorted(success_codes):
                result += format_response(code, responses[code]) + "\n"
        
        if error_codes:
            result += "### Коды ошибок\n\n"
            for code in sorted(error_codes):
                result += format_response(code, responses[code]) + "\n"
    
    return [TextContent(type="text", text=result)]


async def handle_search_endpoints(arguments: Dict[str, Any]) -> List[TextContent]:
    """Обработчик инструмента search_endpoints."""
    query = arguments.get('query', '')
    
    if not query:
        return [TextContent(
            type="text",
            text="Ошибка: необходимо указать поисковый запрос"
        )]
    
    results = parser.search_endpoints(query)
    
    if not results:
        return [TextContent(
            type="text",
            text=f"По запросу '{query}' ничего не найдено."
        )]
    
    result = f"# Результаты поиска: '{query}'\n\n"
    result += f"Найдено эндпоинтов: {len(results)}\n\n"
    
    for i, endpoint in enumerate(results, 1):
        result += f"## {i}. {endpoint['method']} {endpoint['path']}\n\n"
        if endpoint.get('summary'):
            result += f"**{endpoint['summary']}**\n\n"
        if endpoint.get('description'):
            result += f"{endpoint['description']}\n\n"
        if endpoint.get('tags'):
            result += f"**Теги:** {', '.join(endpoint['tags'])}\n\n"
        if endpoint.get('match_reasons'):
            result += f"*Найдено по: {', '.join(endpoint['match_reasons'])}*\n\n"
        result += "---\n\n"
    
    return [TextContent(type="text", text=result)]


async def handle_get_schema_fields(arguments: Dict[str, Any]) -> List[TextContent]:
    """Обработчик инструмента get_schema_fields."""
    schema_name = arguments.get('schema_name', '')
    
    if not schema_name:
        return [TextContent(
            type="text",
            text="Ошибка: необходимо указать schema_name"
        )]
    
    schema_info = parser.get_schema_info(schema_name)
    
    if not schema_info:
        # Пытаемся найти похожие схемы
        all_schemas = parser.get_all_schemas()
        similar = [name for name in all_schemas.keys() if schema_name.lower() in name.lower()]
        
        if similar:
            return [TextContent(
                type="text",
                text=f"Схема '{schema_name}' не найдена. Возможно, вы имели в виду: {', '.join(similar)}"
            )]
        else:
            return [TextContent(
                type="text",
                text=f"Схема '{schema_name}' не найдена в спецификации."
            )]
    
    result = f"# Схема: {schema_info['name']}\n\n"
    
    if schema_info.get('description'):
        result += f"{schema_info['description']}\n\n"
    
    result += f"**Тип:** {schema_info['type']}\n\n"
    
    fields = schema_info.get('fields', [])
    if fields:
        result += "## Поля схемы\n\n"
        
        # Разделяем на обязательные и необязательные
        required_fields = [f for f in fields if f.get('required')]
        optional_fields = [f for f in fields if not f.get('required')]
        
        if required_fields:
            result += "### Обязательные поля\n\n"
            for field in required_fields:
                field_type = field.get('type', 'unknown')
                description = field.get('description', '')
                example = field.get('example')
                enum = field.get('enum')
                
                field_str = f"- **{field['name']}** ({field_type})"
                if description:
                    field_str += f": {description}"
                if example is not None:
                    field_str += f" (пример: `{example}`)"
                if enum:
                    field_str += f" [допустимые значения: {', '.join(map(str, enum))}]"
                
                result += field_str + "\n"
            result += "\n"
        
        if optional_fields:
            result += "### Необязательные поля\n\n"
            for field in optional_fields:
                field_type = field.get('type', 'unknown')
                description = field.get('description', '')
                read_only = field.get('readOnly', False)
                nullable = field.get('nullable', False)
                example = field.get('example')
                enum = field.get('enum')
                
                field_str = f"- **{field['name']}** ({field_type})"
                if read_only:
                    field_str += " [только чтение]"
                if nullable:
                    field_str += " [nullable]"
                if description:
                    field_str += f": {description}"
                if example is not None:
                    field_str += f" (пример: `{example}`)"
                if enum:
                    field_str += f" [допустимые значения: {', '.join(map(str, enum))}]"
                
                result += field_str + "\n"
            result += "\n"
    else:
        result += "Поля не определены.\n\n"
    
    return [TextContent(type="text", text=result)]


async def handle_list_endpoints(arguments: Dict[str, Any]) -> List[TextContent]:
    """Обработчик инструмента list_endpoints."""
    tag = arguments.get('tag')  # Опциональный фильтр по тегу
    
    if tag:
        grouped = parser.list_endpoints_by_tag()
        if tag not in grouped:
            return [TextContent(
                type="text",
                text=f"Тег '{tag}' не найден. Доступные теги: {', '.join(grouped.keys())}"
            )]
        
        endpoints = grouped[tag]
        result = f"# Эндпоинты с тегом: {tag}\n\n"
        result += f"Всего: {len(endpoints)}\n\n"
    else:
        grouped = parser.list_endpoints_by_tag()
        result = "# Все эндпоинты API\n\n"
        result += f"Всего тегов: {len(grouped)}\n\n"
        
        for tag_name, endpoints in grouped.items():
            result += f"## {tag_name} ({len(endpoints)} эндпоинтов)\n\n"
            for endpoint in endpoints:
                result += f"- **{endpoint['method']}** `{endpoint['path']}`"
                if endpoint.get('summary'):
                    result += f" - {endpoint['summary']}"
                result += "\n"
            result += "\n"
        
        return [TextContent(type="text", text=result)]
    
    # Если указан тег, показываем детальную информацию
    for i, endpoint in enumerate(endpoints, 1):
        result += f"## {i}. {endpoint['method']} {endpoint['path']}\n\n"
        if endpoint.get('summary'):
            result += f"**{endpoint['summary']}**\n\n"
        if endpoint.get('description'):
            result += f"{endpoint['description']}\n\n"
        result += "---\n\n"
    
    return [TextContent(type="text", text=result)]


# Создаем MCP сервер
server = Server("moysklad-api-mcp")


@server.list_tools()
async def list_tools() -> List[Tool]:
    """Список доступных инструментов."""
    return [
        Tool(
            name="get_endpoint_info",
            description="Получить полную информацию об эндпоинте API: метод, параметры, схема запроса/ответа, коды ошибок",
            inputSchema={
                "type": "object",
                "properties": {
                    "path": {
                        "type": "string",
                        "description": "Путь эндпоинта, например /entity/product"
                    },
                    "method": {
                        "type": "string",
                        "description": "HTTP метод: GET, POST, PUT, DELETE, PATCH",
                        "enum": ["GET", "POST", "PUT", "DELETE", "PATCH"]
                    }
                },
                "required": ["path", "method"]
            }
        ),
        Tool(
            name="search_endpoints",
            description="Найти эндпоинты по ключевым словам. Ищет в путях, названиях, описаниях и тегах методов",
            inputSchema={
                "type": "object",
                "properties": {
                    "query": {
                        "type": "string",
                        "description": "Поисковый запрос (например: 'product', 'создать', 'удалить')"
                    }
                },
                "required": ["query"]
            }
        ),
        Tool(
            name="get_schema_fields",
            description="Получить список полей схемы с типами, обязательностью, примерами и описаниями",
            inputSchema={
                "type": "object",
                "properties": {
                    "schema_name": {
                        "type": "string",
                        "description": "Имя схемы, например Product, Group, Counterparty"
                    }
                },
                "required": ["schema_name"]
            }
        ),
        Tool(
            name="list_endpoints",
            description="Получить список всех эндпоинтов API, сгруппированных по тегам. Можно отфильтровать по конкретному тегу",
            inputSchema={
                "type": "object",
                "properties": {
                    "tag": {
                        "type": "string",
                        "description": "Опционально: фильтр по тегу (например: 'Products'). Если не указан, вернет все эндпоинты"
                    }
                }
            }
        )
    ]


@server.call_tool()
async def call_tool(name: str, arguments: Dict[str, Any]) -> List[TextContent]:
    """Обработчик вызовов инструментов."""
    handlers = {
        "get_endpoint_info": handle_get_endpoint_info,
        "search_endpoints": handle_search_endpoints,
        "get_schema_fields": handle_get_schema_fields,
        "list_endpoints": handle_list_endpoints,
    }
    
    handler = handlers.get(name)
    if not handler:
        return [TextContent(
            type="text",
            text=f"Неизвестный инструмент: {name}"
        )]
    
    return await handler(arguments)


async def main():
    """Точка входа для запуска MCP сервера."""
    async with stdio_server() as (read_stream, write_stream):
        await server.run(
            read_stream,
            write_stream,
            server.create_initialization_options()
        )


if __name__ == "__main__":
    asyncio.run(main())
