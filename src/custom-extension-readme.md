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


## x-polymorphic-discriminator

**Предназначение**: описать полиморфную десериализацию, когда тип объекта лежит не в поле
верхнего уровня, а во вложенном пути JSON, например `meta.type`.

Стандартный OpenAPI `discriminator` поддерживает только имя свойства схемы, поэтому для API, где
дискриминатор находится во вложенной ноде, используется это расширение.

**Важно**:
* `x-polymorphic-discriminator` и стандартный `discriminator` взаимоисключающие.
  На одной схеме нельзя объявлять оба механизма. Проверка выполняется при `npm run validate`.
* расширение работает в связке с `x-polymorphic-parent` на concrete-схемах из `mappings`.

**Структура**:
* path - строка в dot-нотации вложенности. Пример формата `meta.type`
* batchErrorFallback - опциональный флаг. Если `true` и компонент не удалось
  определить по `path` и `mappings`, SDK проверяет JSON на маркеры batch-ошибки
  (`errors` - массив объектов, где есть строковое поле `error`) и десериализует
  такой объект в компонент `Error`.
* mappings - массив соответствий `{type, componentName}` для генерации SDK 
  * `type` — значение дискриминатора 
  * `componentName` — имя компонента из `components.schemas`.

**Пример**

```yaml
x-polymorphic-discriminator:
  path: meta.type
  batchErrorFallback: true
  mappings:
    - type: customerorder
      componentName: FinanceInOperationCustomerOrder
```


## x-polymorphic-parent

**Предназначение**: явно связать конкретную схему с абстрактной полиморфной базой

Расширение используется на неабстрактных компонентах и должно быть в списке в `x-polymorphic-discriminator.mappings` родительского компонента.
Если mapping указывает на вложенного наследника, у схемы должна существовать цепочка
`x-polymorphic-parent` до базовой схемы с `x-polymorphic-discriminator`.

**Структура**:
* x-polymorphic-parent - имя абстрактной родительской схемы из `components.schemas`. Допустимый формат записи - строка,
без указания полного пути компонента или ссылки вида $ref. 
Принимается только точное совпадение названия этого компонента в `components.schemas`

**Особенности**

Перечислять проперти или ссылку на родителя в блоке `allOf` не треубется, при указании родителя в `x-polymorphic-parent`
в шаблоне автоматически будут связаны и добавлены все наследуемые проперти, методы от родителя 

**Пример**

```yaml
type: object
description: Отгрузка + linkedSum
x-polymorphic-parent: FinanceInOperationAbstract
allOf:
  - $ref: '../../../openapi.yaml#/components/schemas/Demand'
```
