# Mock Plugin for WP GitHub Updater Tests

## 📋 Descripción

Este es un **plugin de prueba (fixture)** que demuestra la integración correcta del paquete `silverassist/wp-github-updater` en un entorno real de WordPress.

## 🎯 Propósito

El mock plugin sirve para:

1. **Testing Real de WordPress**: Permite probar el updater en un entorno WordPress completo con WordPress Test Suite
2. **Ejemplo de Integración**: Demuestra el patrón recomendado de integración del paquete
3. **Documentación Viva**: Sirve como referencia para desarrolladores que integren el paquete

## 📁 Estructura

```
tests/fixtures/mock-plugin/
├── mock-plugin.php       # Plugin principal con integración completa
├── readme.txt           # WordPress plugin readme
└── README.md           # Esta documentación
```

## 🔧 Características Implementadas

### ✅ Integración Completa del Updater

```php
// Configuración completa con todas las opciones
$config = new UpdaterConfig(
    __FILE__,
    "SilverAssist/mock-test-repo",
    [
        "plugin_name" => "Mock Plugin for Testing",
        "cache_duration" => 300, // 5 minutos para testing
        "text_domain" => "mock-plugin",
        "custom_temp_dir" => WP_CONTENT_DIR . "/uploads/temp",
        // ... más opciones
    ]
);

$updater = new Updater($config);
```

### ✅ Panel de Administración

- Página de admin con información del plugin
- Botón para check manual de actualizaciones
- Display de versión actual y disponible
- Integración AJAX para checks en tiempo real

### ✅ Hooks de WordPress

- `plugins_loaded`: Inicialización del updater
- `admin_menu`: Menú de administración
- `activate_*`: Limpieza de cache en activación
- `deactivate_*`: Limpieza de cache en desactivación

### ✅ AJAX Handlers

- `mock_plugin_check_version`: Check manual de versión
- Verificación de nonces y capabilities
- Respuestas JSON formateadas

## 🧪 Uso en Tests

### Cargar el Mock Plugin

El mock plugin se carga automáticamente en el bootstrap de WordPress Test Suite:

```php
// En tests/bootstrap.php
function _manually_load_plugin() {
    require_once __DIR__ . "/fixtures/mock-plugin/mock-plugin.php";
}
tests_add_filter("muplugins_loaded", "_manually_load_plugin");
```

### Acceder al Updater en Tests

```php
class MyTest extends WP_UnitTestCase {
    public function testUpdater() {
        // Obtener instancia del updater
        $updater = mock_plugin_get_updater();
        
        // Verificar funcionalidad
        $this->assertInstanceOf(Updater::class, $updater);
        $this->assertEquals("1.0.0", $updater->getCurrentVersion());
    }
}
```

### Ejemplo de Test Completo

Ver `tests/WordPress/MockPluginTest.php` para ejemplos completos de:

- ✅ Test de inicialización
- ✅ Test de hooks registrados
- ✅ Test de AJAX actions
- ✅ Test de activación/desactivación
- ✅ Test de menu de admin
- ✅ Test de caching con transients

## 📊 Tests Incluidos

El mock plugin tiene su propia suite de tests en `tests/WordPress/MockPluginTest.php`:

| Test | Descripción |
|------|-------------|
| `testMockPluginFileExists` | Verifica que el archivo existe |
| `testMockPluginCanBeLoaded` | Verifica que se puede cargar |
| `testUpdaterIsInitialized` | Verifica inicialización del updater |
| `testUpdaterConfiguration` | Verifica configuración correcta |
| `testWordPressHooksAreRegistered` | Verifica hooks de WordPress |
| `testAjaxActionsAreRegistered` | Verifica AJAX actions |
| `testPluginActivation` | Verifica activación del plugin |
| `testPluginDeactivation` | Verifica desactivación del plugin |
| `testAdminMenuIsRegistered` | Verifica menú de admin |
| `testUpdateCheckWithCaching` | Verifica caching con transients |
| `testPluginDataRetrieval` | Verifica lectura de metadata |
| `testPluginBasename` | Verifica plugin_basename() |
| `testCustomTempDirectoryConfiguration` | Verifica directorio temporal (v1.1.3+) |

## 🚀 Ejecutar Tests con WordPress Test Suite

### 1. Instalar WordPress Test Suite

```bash
./bin/install-wp-tests.sh wordpress_test root '' localhost 6.7.1
```

### 2. Ejecutar Tests de WordPress

```bash
# Todos los tests de WordPress (incluye mock plugin)
./vendor/bin/phpunit --testsuite=wordpress

# Solo tests del mock plugin
./vendor/bin/phpunit tests/WordPress/MockPluginTest.php

# Todos los tests (incluye modo WordPress si está instalado)
./vendor/bin/phpunit
```

### 3. Verificar Salida

Cuando WordPress Test Suite está disponible, verás:

```
====================================
WP GitHub Updater Test Suite
====================================
Mode: WordPress Integration Tests
WP Tests Dir: /tmp/wordpress-tests-lib
====================================
✓ Mock plugin loaded: /path/to/tests/fixtures/mock-plugin/mock-plugin.php
```

## 🔍 Funcionalidades para Testing

### Metadata del Plugin

```php
$pluginData = get_plugin_data($pluginFile);

// Retorna:
[
    "Name" => "Mock Plugin for WP GitHub Updater Tests",
    "Version" => "1.0.0",
    "Author" => "SilverAssist",
    "RequiresWP" => "6.0",
    "RequiresPHP" => "8.3",
    // ...
]
```

### Acceso Global al Updater

```php
// Obtener updater desde cualquier parte
$updater = mock_plugin_get_updater();

// O desde global
$updater = $GLOBALS["mock_plugin_updater"];
```

### Limpieza de Cache

```php
// Limpiar cache de versiones
delete_transient("mock-plugin_version_check");

// O usar función de activación
do_action("activate_mock-plugin/mock-plugin.php");
```

## 📝 Configuración

### Opciones Configurables

El mock plugin demuestra todas las opciones disponibles:

```php
[
    "plugin_name" => "Mock Plugin for Testing",
    "plugin_description" => "A mock plugin for WP GitHub Updater tests",
    "plugin_author" => "SilverAssist",
    "cache_duration" => 300,              // 5 minutos
    "text_domain" => "mock-plugin",
    "custom_temp_dir" => WP_CONTENT_DIR . "/uploads/temp",
    "ajax_action" => "mock_plugin_check_version",
    "ajax_nonce" => "mock_plugin_nonce",
    "asset_pattern" => "mock-plugin-{version}.zip",
    "requires_wordpress" => "6.0",
    "requires_php" => "8.3",
]
```

## ⚠️ Notas Importantes

### No Usar en Producción

Este plugin es **exclusivamente para testing** y no debe usarse en sitios de producción:

- Usa un repositorio GitHub ficticio (`mock-test-repo`)
- Cache duration muy corta (5 minutos)
- Configuración optimizada para testing

### Repositorio Ficticio

El plugin apunta a `SilverAssist/mock-test-repo` que puede no existir. Para tests reales de API, deberás:

1. Crear un repositorio de prueba en GitHub
2. Actualizar la configuración en `mock-plugin.php`
3. Crear releases de prueba en ese repositorio

### Compatibilidad

- **WordPress**: 6.0+
- **PHP**: 8.3+
- **PHPUnit**: 9.6+
- **WordPress Test Suite**: Requerido para tests completos

## 🔗 Referencias

- [Ejemplo de Integración](../../../examples/integration-guide.php)
- [Documentación del Paquete](../../../README.md)
- [Testing Summary](../../../docs/TESTING-SUMMARY.md)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)

## 📄 Licencia

MIT - Solo para propósitos de testing

---

**Última actualización:** 2025-01-10  
**Versión:** 1.0.0  
**Paquete:** silverassist/wp-github-updater v1.1.5
