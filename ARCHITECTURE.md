# Yii 2 Framework Architecture

## Purpose

Yii 2 is a high-performance PHP component-based framework. It emphasises reusability, extensibility and
testability through a strict component model (property/event/behavior), a powerful ActiveRecord ORM,
and a layered application structure. It targets PHP 8.1+ in current maintenance builds.

## Directory Structure

```
framework/
  base/              Core component model — the foundation every other subsystem builds on
    Base_Object.php    Root class: magic getter/setter via getX()/setX() conventions
    Component.php      Adds events and behaviors to Base_Object
    Model.php          Data model with validation, scenarios and mass-assignment
    Application.php    Application bootstrap: DI container, module registry, error handler
    Module.php         Reusable self-contained feature unit (routes + controllers + models)
    Controller.php     Base controller — action dispatch and filter pipeline
    Action.php         Encapsulates a single controller action
    Behavior.php       Mixable behavior attached to any Component
    Event.php          Event data container; also holds class-level handler registry
    Security.php       Cryptographic primitives: PBKDF2, random bytes, comparison
    View.php           Template rendering engine with layout support
    Widget.php         Self-contained UI component that renders its own output
    ErrorHandler.php   Converts PHP errors and uncaught exceptions to structured responses
  db/                Active Record ORM and query builder
    ActiveRecord.php   AR base class: find, save, delete, relations, transactions
    ActiveQuery.php    AR-specific query builder with eager/lazy relation loading
    Connection.php     PDO-based database connection with schema cache
    Schema.php         Database schema introspection abstraction
    Migration.php      Schema migration base class
    QueryBuilder.php   SQL generation for each supported DBMS
    Command.php        Prepared statement execution wrapper
    Transaction.php    Explicit transaction control with save-point support
  web/               Web-specific specialisations
    Application.php    Web app: request parsing, URL management, session, auth
    Controller.php     Web controller with view rendering helpers
    Request.php        HTTP request with CSRF, cookie, header and body parsing
    Response.php       HTTP response: format negotiation (HTML/JSON/XML), cookies, redirects
    Session.php        Session management with flash message support
    User.php           Authentication state (identity, guest detection, login/logout)
    UrlManager.php     URL creation and route matching
    View.php           Web view extending base view with asset bundles
```

## Key Design Decisions

- **Property convention via magic methods**: `Base_Object` maps `$obj->foo` to `getFoo()`/`setFoo()`
  without requiring explicit property declarations. This enables calculated properties and encapsulation
  while keeping call syntax clean.
- **Event system at two levels**: Instance events (via `Component::on()`) and class-level events
  (via `Event::on()`) coexist. Class-level events let you hook into all instances of a class without
  holding a reference to each one.
- **Pluggable behaviors**: Any `Component` can have `Behavior` objects attached at runtime or via
  the `behaviors()` method. Behaviors proxy their public methods and properties onto the host component
  — enabling mixin-style reuse without multiple inheritance.
- **Scenario-driven validation**: `Model::rules()` declares rules tagged by scenario. Setting
  `$model->scenario` activates a subset of rules, so the same model class can be used for different
  workflows (e.g., `'create'` vs `'update'`) with distinct validation requirements.
- **ActiveRecord with relation loading**: `ActiveRecord::find()` returns an `ActiveQuery` that supports
  `with()` for eager loading and `via()` / `viaTable()` for junction-table relations.
- **DI container**: `Yii::$container` is a service locator + DI container. `Yii::createObject($config)`
  resolves classes with their dependencies injected automatically.

## Extension Points

| Mechanism | How to extend |
|-----------|---------------|
| Custom component | Extend `Component`, declare properties via getters/setters |
| Custom behavior | Extend `Behavior`, attach via `Component::attachBehavior()` or `behaviors()` |
| Custom validation rule | Extend `Validator`, register in `Validator::$builtInValidators` |
| Custom AR relation | Override `relations()` on an `ActiveRecord` subclass |
| Custom response format | Register a formatter in `Response::$formatters` |
| Custom URL rule | Implement `UrlRuleInterface`, add to `UrlManager::$rules` |
| Custom filter | Extend `ActionFilter`, add to `Controller::behaviors()` |
| Modules | Extend `Module`, register in `Application::$modules` config |

## Dependency Flow

```
Entry point (index.php)
  └─ web\Application
       ├─ web\Request      (parses headers, body, CSRF token)
       ├─ UrlManager       (matches route → controller/action)
       ├─ Module registry  (resolves controller namespace)
       └─ Controller
            ├─ ActionFilter pipeline (auth, access control, rate limit…)
            └─ Action
                 ├─ Model / ActiveRecord
                 │     └─ db\Connection → QueryBuilder → Command
                 └─ web\View → layout + view file rendering
```

## Key Classes

- `yii\base\Component` — the universal base for all configurable framework objects: properties, events, behaviors
- `yii\base\Model` — data container with declarative validation rules and scenario support
- `yii\db\ActiveRecord` — ORM base: maps database rows to objects, manages relations and transactions
- `yii\web\Request` — encapsulates the HTTP request including CSRF protection and body parsing
- `yii\web\Response` — content-format-aware HTTP response with automatic serialisation
- `yii\base\Security` — constant-time comparison, cryptographic random generation, PBKDF2 hashing
