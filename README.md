# JP Fútbol · Web y app
KREAVANCE

## Seguridad de los mensajes (24-11-2026)

- `app/mensajes.php` guarda mensajes y avisos en `jpfutbol-mensajes.json`, fuera de la carpeta pública.
- **Borrar todos los mensajes** («Borrar los mensajes de prueba» y Ajustes) pide el **código del club**. Ese código vive solo en el servidor: archivo `jpfutbol-mensajes-codigo.txt` (una línea) junto a `jpfutbol-mensajes.json`, fuera de la carpeta pública. Si no está ese archivo, vale el código que eligió Kike el 24-11-2026, guardado en `mensajes.php` solo como huella (bcrypt), nunca escrito. 5 códigos mal desde la misma conexión = 1 hora bloqueada.
- Pendiente antes de usar la app con familias reales: leer y escribir mensajes con inicio de sesión de verdad (hoy solo se comprueba que la petición venga de la app, y eso se puede falsificar).
