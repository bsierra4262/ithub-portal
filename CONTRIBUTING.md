# Guía de contribución

Convenciones del proyecto **ITHub**.

## Flujo de trabajo

1. Crear una rama desde `main`: `git checkout -b feat/mi-cambio`.
2. Realizar cambios atómicos y acompañados de tests.
3. Ejecutar la batería antes de abrir el Pull Request: `php test/run_tests.php`.
4. Abrir un Pull Request describiendo el qué y el porqué del cambio, con captura si toca la interfaz.

## Mensajes de commit — [Conventional Commits](https://www.conventionalcommits.org/es/)

```
<tipo>(ámbito opcional): <descripción en imperativo, minúscula, sin punto>
```

Tipos: `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`.

Ejemplos:

```
feat(oidc): back-channel logout a las apps conectadas
fix(permisos): un rol que la app ya no tiene deja de valer
docs(integrar): ejemplo de validación del id_token en PHP
test(oidc): reuso de un código revoca los tokens emitidos
```

## Reglas del proyecto

- **Cada defecto detectado incorpora un test de regresión** en `test/run_tests.php`.
- **Toda funcionalidad se documenta en el mismo cambio**: README, changelog y, si toca el
  protocolo, [md/INTEGRAR-APLICACION.md](md/INTEGRAR-APLICACION.md).
- **Sin secretos en el repositorio.** `config.php`, la clave de firma y los secretos de las
  apps viven en el servidor (fuera del web root) y en el repo privado `ithub-portal-claves`.
- **Nada de JavaScript ni estilos en línea**: la política de seguridad de contenido los
  bloquea. Todo va en `assets/`.
- **Cambios al protocolo con cuidado**: el `issuer`, los nombres de los claims y el formato
  de los tokens son un contrato con todas las apps conectadas.
