"""
Парсер OpenAPI спецификации с поддержкой разрешения $ref ссылок.
"""
import os
import yaml
from pathlib import Path
from typing import Dict, Any, Optional, List
from urllib.parse import urlparse


class OpenAPIParser:
    """Парсер OpenAPI спецификации с резолвером $ref."""
    
    def __init__(self, spec_path: str):
        """
        Инициализация парсера.
        
        Args:
            spec_path: Путь к основному файлу openapi.yaml
        """
        self.spec_path = Path(spec_path).resolve()
        self.base_dir = self.spec_path.parent  # Директория src/
        self.spec: Dict[str, Any] = {}
        self.resolved_spec: Dict[str, Any] = {}
        self._cache: Dict[str, Any] = {}
        self._file_stack: List[Path] = []  # Стек файлов для отслеживания контекста
        
    def load(self) -> Dict[str, Any]:
        """Загрузить и разрешить OpenAPI спецификацию."""
        # Устанавливаем текущий файл как основной
        self._file_stack = [self.spec_path]
        
        # Загружаем основной файл
        with open(self.spec_path, 'r', encoding='utf-8') as f:
            self.spec = yaml.safe_load(f)
        
        # Разрешаем все $ref ссылки (передаем spec_path как текущий файл)
        self.resolved_spec = self._resolve_refs(self.spec, current_file=self.spec_path)
        
        return self.resolved_spec
    
    def _load_file(self, file_path: str, relative_to: Optional[Path] = None) -> Dict[str, Any]:
        """Загрузить YAML файл с кешированием."""
        # Если указан файл относительно которого вычисляем путь, используем его
        # Иначе используем base_dir
        if relative_to:
            base = relative_to.parent
        else:
            base = self.base_dir
        
        # Обрабатываем относительные пути
        if file_path.startswith('../'):
            # Подсчитываем количество ../
            parts = file_path.split('/')
            up_levels = 0
            remaining_path = []
            for part in parts:
                if part == '..':
                    up_levels += 1
                elif part:
                    remaining_path.append(part)
            
            # Поднимаемся на нужное количество уровней от базовой директории
            target_dir = base
            for _ in range(up_levels):
                target_dir = target_dir.parent
            
            abs_path = (target_dir / '/'.join(remaining_path)).resolve()
        elif file_path.startswith('./'):
            # Относительно текущей директории
            abs_path = (base / file_path[2:]).resolve()
        else:
            # Пробуем несколько вариантов
            possible_paths = [
                base / file_path,
                self.base_dir / file_path,
            ]
            
            abs_path = None
            for path in possible_paths:
                resolved = path.resolve()
                if resolved.exists():
                    abs_path = resolved
                    break
            
            if not abs_path:
                raise FileNotFoundError(f"File not found: {file_path} (tried: {[str(p) for p in possible_paths]})")
        
        if not abs_path.exists():
            raise FileNotFoundError(f"File not found: {file_path} (resolved to: {abs_path})")
        
        if str(abs_path) in self._cache:
            return self._cache[str(abs_path)]
        
        with open(abs_path, 'r', encoding='utf-8') as f:
            content = yaml.safe_load(f)
            self._cache[str(abs_path)] = content
            return content
    
    def _resolve_ref(self, ref: str, current_file: Optional[Path] = None) -> tuple[Any, Optional[Path]]:
        """
        Разрешить $ref ссылку.
        
        Поддерживает:
        - Относительные пути к файлам: './paths/products/products.yaml'
        - Внутренние ссылки: '#/components/schemas/Product'
        - Комбинированные: './schemas/product.yaml#/components/schemas/Product'
        - Ссылки на компоненты: '../../../components/responses.yaml#/BadRequest'
        """
        if not ref.startswith('#') and not ref.startswith('./') and not ref.startswith('../'):
            # Абсолютный URL или другой формат - не обрабатываем
            return ({"$ref": ref}, current_file)
        
        # Разделяем путь к файлу и JSON pointer
        if '#' in ref:
            file_part, pointer = ref.split('#', 1)
        else:
            file_part = ref
            pointer = None
        
        # Загружаем файл если указан
        if file_part:
            # Вычисляем путь к файлу для обновления current_file
            if current_file:
                base = current_file.parent
            else:
                base = self.base_dir
            
            if file_part.startswith('../'):
                parts = file_part.split('/')
                up_levels = sum(1 for p in parts if p == '..')
                remaining = [p for p in parts if p != '..' and p]
                target_dir = base
                for _ in range(up_levels):
                    target_dir = target_dir.parent
                loaded_file_path = (target_dir / '/'.join(remaining)).resolve()
            elif file_part.startswith('./'):
                loaded_file_path = (base / file_part[2:]).resolve()
            else:
                loaded_file_path = (base / file_part).resolve()
            
            data = self._load_file(file_part, relative_to=current_file)
            
            # Обновляем current_file для вложенных ссылок в загруженном файле
            if isinstance(data, dict):
                current_file = loaded_file_path
        else:
            data = self.resolved_spec
        
        # Разрешаем JSON pointer
        if pointer:
            pointer = pointer.lstrip('#/')
            parts = pointer.split('/')
            for part in parts:
                if isinstance(data, dict) and part in data:
                    data = data[part]
                else:
                    return ({"$ref": ref}, current_file)  # Не удалось разрешить
        
        return (data, current_file)
    
    def _resolve_refs(self, obj: Any, parent_path: str = "", current_file: Optional[Path] = None) -> Any:
        """Рекурсивно разрешить все $ref ссылки в объекте."""
        if isinstance(obj, dict):
            if '$ref' in obj:
                # Разрешаем ссылку
                ref_value = obj['$ref']
                resolved, new_current_file = self._resolve_ref(ref_value, current_file)
                # Если разрешили, продолжаем рекурсию для вложенных ссылок
                if isinstance(resolved, dict) and '$ref' not in resolved:
                    return self._resolve_refs(resolved, parent_path, new_current_file)
                return resolved
            
            # Рекурсивно обрабатываем все ключи
            return {
                key: self._resolve_refs(value, f"{parent_path}.{key}" if parent_path else key, current_file)
                for key, value in obj.items()
            }
        elif isinstance(obj, list):
            return [self._resolve_refs(item, parent_path, current_file) for item in obj]
        else:
            return obj
    
    def get_endpoints(self) -> Dict[str, Dict[str, Any]]:
        """Получить все эндпоинты с разрешенными ссылками."""
        paths = self.resolved_spec.get('paths', {})
        return paths
    
    def get_endpoint_info(self, path: str, method: str) -> Optional[Dict[str, Any]]:
        """
        Получить информацию об эндпоинте.
        
        Args:
            path: Путь эндпоинта (например, /entity/product)
            method: HTTP метод (GET, POST, PUT, DELETE)
        
        Returns:
            Словарь с информацией об эндпоинте или None
        """
        paths = self.get_endpoints()
        method = method.lower()
        
        if path not in paths:
            return None
        
        endpoint = paths[path]
        if method not in endpoint:
            return None
        
        method_info = endpoint[method]
        
        return {
            'path': path,
            'method': method.upper(),
            'summary': method_info.get('summary', ''),
            'description': method_info.get('description', ''),
            'tags': method_info.get('tags', []),
            'parameters': method_info.get('parameters', []),
            'requestBody': method_info.get('requestBody'),
            'responses': method_info.get('responses', {}),
            'operationId': method_info.get('operationId'),
        }
    
    def search_endpoints(self, query: str) -> List[Dict[str, Any]]:
        """
        Поиск эндпоинтов по ключевым словам.
        
        Ищет в:
        - Пути эндпоинта
        - Названии метода (summary)
        - Описании (description)
        - Тегах
        """
        query_lower = query.lower()
        results = []
        
        paths = self.get_endpoints()
        
        for path, methods in paths.items():
            for method, method_info in methods.items():
                if not isinstance(method_info, dict):
                    continue
                
                # Проверяем совпадения
                matches = False
                match_reasons = []
                
                if query_lower in path.lower():
                    matches = True
                    match_reasons.append(f"путь: {path}")
                
                summary = method_info.get('summary', '')
                if query_lower in summary.lower():
                    matches = True
                    match_reasons.append(f"название: {summary}")
                
                description = method_info.get('description', '')
                if query_lower in description.lower():
                    matches = True
                    match_reasons.append(f"описание: {description[:100]}...")
                
                tags = method_info.get('tags', [])
                for tag in tags:
                    if query_lower in tag.lower():
                        matches = True
                        match_reasons.append(f"тег: {tag}")
                
                if matches:
                    results.append({
                        'path': path,
                        'method': method.upper(),
                        'summary': summary,
                        'description': description[:200] if description else '',
                        'tags': tags,
                        'match_reasons': match_reasons
                    })
        
        return results
    
    def get_schema_info(self, schema_name: str) -> Optional[Dict[str, Any]]:
        """
        Получить информацию о схеме.
        
        Args:
            schema_name: Имя схемы (например, Product)
        
        Returns:
            Словарь с информацией о схеме
        """
        schemas = self.resolved_spec.get('components', {}).get('schemas', {})
        
        if schema_name not in schemas:
            return None
        
        schema = schemas[schema_name]
        
        # Извлекаем поля схемы
        properties = schema.get('properties', {})
        required = schema.get('required', [])
        
        fields = []
        for field_name, field_info in properties.items():
            if isinstance(field_info, dict):
                field_type = field_info.get('type', 'object')
                if '$ref' in field_info:
                    # Если есть ссылка, пытаемся извлечь имя схемы
                    ref = field_info['$ref']
                    if '#' in ref:
                        ref_parts = ref.split('#/')
                        if len(ref_parts) > 1:
                            schema_path = ref_parts[-1].split('/')
                            if len(schema_path) >= 3 and schema_path[0] == 'components' and schema_path[1] == 'schemas':
                                field_type = schema_path[2]
                
                fields.append({
                    'name': field_name,
                    'type': field_type,
                    'description': field_info.get('description', ''),
                    'required': field_name in required,
                    'readOnly': field_info.get('readOnly', False),
                    'example': field_info.get('example'),
                    'enum': field_info.get('enum'),
                    'format': field_info.get('format'),
                    'nullable': field_info.get('nullable', False),
                    'deprecated': field_info.get('deprecated', False),
                })
        
        return {
            'name': schema_name,
            'type': schema.get('type', 'object'),
            'description': schema.get('description', ''),
            'fields': fields,
            'required_fields': required,
        }
    
    def list_endpoints_by_tag(self) -> Dict[str, List[Dict[str, Any]]]:
        """Получить список эндпоинтов, сгруппированных по тегам."""
        paths = self.get_endpoints()
        grouped = {}
        
        for path, methods in paths.items():
            for method, method_info in methods.items():
                if not isinstance(method_info, dict):
                    continue
                
                tags = method_info.get('tags', ['Untagged'])
                
                for tag in tags:
                    if tag not in grouped:
                        grouped[tag] = []
                    
                    grouped[tag].append({
                        'path': path,
                        'method': method.upper(),
                        'summary': method_info.get('summary', ''),
                        'description': method_info.get('description', ''),
                    })
        
        return grouped
    
    def get_all_schemas(self) -> Dict[str, Any]:
        """Получить все схемы."""
        return self.resolved_spec.get('components', {}).get('schemas', {})
