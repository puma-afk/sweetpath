# Reglas del proyecto

## Cómo trabajamos (común a todos los proyectos)
- Al empezar: leer docs/ESTADO.md y docs/DECISIONES.md, revisar `git status`, la rama y `git log -n 10`. Resumir en 10 líneas qué funciona, qué está en proceso y qué sigue. No modificar nada hasta que el usuario confirme.
- Proponer un plan antes de cambios grandes y esperar confirmación.
- Cambios pequeños y verificables; un commit por tarea terminada.
- No crear archivos ni carpetas fuera de `salidas/` sin preguntar.
- No modificar `docs/` salvo al cerrar la sesión o si se pide.
- Nunca subir secretos: `.env`, claves, tokens o contraseñas reales.
- Si algo no está claro en el contexto, preguntar; no inventar.
- Al terminar: actualizar docs/ESTADO.md (completado, a medias con archivo exacto, siguiente paso), registrar decisiones nuevas en docs/DECISIONES.md y hacer commit.

## Delegación entre agentes
- Claude: diseña, divide en tareas y revisa. Codex: implementa tareas bien definidas. Gemini: lee y analiza material grande.
- Cada tarea delegada tiene un brief en `docs/tareas/T-NNN.md` (objetivo, archivos a tocar, qué no tocar, criterios de aceptación, entregable).
- Una tarea, un agente, una rama `tarea/T-NNN`. Nada se integra sin revisar el diff y pasar las pruebas.

## Este proyecto: SweetPath (Pastelería Esencia)
- Qué es: tienda web de la pastelería con panel de administración, pedidos, promociones y pagos.
- Stack: PHP (admin/, api/, lib/, db.php), front estático en public/. Por confirmar: base de datos y servidor.
- Ejecutar y probar: por confirmar.
- Material de marca y documento de la tienda: carpeta superior `pasteleria esencia/` (logos y "tienda web pasteleria.docx").
- No tocar / prohibido:
  - No subir credenciales de base de datos ni de pagos.
  - Rama `respaldo-local`: cambios antiguos guardados; no fusionar sin revisar.
