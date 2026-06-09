# Описание кастомных расширений

## x-entity-static-builder

**Предназначение**: генерация статического метода с созданием объекта модели с уже заполненной метой

Универсальный конструктор, подходит для создания любых сущностей с заполненной meta

**Структура**:
* methodParams - массив. Названия строковых параметров необходмых для построения meta 
* href - массив объектов. Итерируемся по элементам массива для склейки в готовый href в meta
  * path - строка. Что записано в значении этого поля то и запишется в href
  * param - строка. Значение этого поля - название, ссылка на переменную с methodParams
* type - строка. Значение этого поля подставляется как type в meta

**Пример**

```yaml
x-entity-static-builder:
  methodParams:
    - "parentId"
    - "id"
  href:
    - path: "entity"
    - path: "customerorder"
    - param: "parentId"
    - path: "positions"
    - param: "id"
  type: "customerorderposition"
```


## x-agent-reference

**Предназначение**: явно пометить свойства `agent` и указать, что это поле является ссылкой на `Counterparty/Organization` 
(через `Agent`) или обычной ссылкой другого типа.

Используется для свойств с именем `agent` и задается как булевый флаг `true` или `false`.
Расширение обязательно: для таких полей отсутствие `x-agent-reference` считается ошибкой валидации спецификации.

**Структура**:
* x-agent-reference - boolean
  * true - поле трактуется как ссылка на `agent` (`anyOf` из `Counterparty` и `Organization`):
    * в схеме поле задается через `allOf` с `$ref` на `#/components/schemas/Agent`;
    * `Agent` в спецификации описан как `anyOf` из `Counterparty` и `Organization`.
  * false - поле `agent` не является ссылкой на `Agent`:
    * для него используется обычный `$ref` на фактический тип (например, `Employee`).

**Пример**

```yaml
agent:
  description: Метаданные контрагента или юрлица
  x-agent-reference: true
  allOf:
    - $ref: '../../../openapi.yaml#/components/schemas/Agent'
```