<?php
/* JP Fútbol · Sesión de la versión de presentación
   POST {codigo}  → comprueba el código de acceso en el servidor y, si es correcto, deja una cookie de sesión
                    (HttpOnly, Secure, SameSite=Strict, 30 días). Máximo 8 intentos fallidos por IP cada 15 minutos.
   GET            → {ok: true|false} según haya sesión válida.
   Otros archivos (mensajes.php) lo incluyen con JP_SESION_LIB para usar jp_sesion_valida().
   La clave para firmar la cookie se crea sola y se guarda fuera de la carpeta pública; no está en el repositorio.
   Cuando haya cuentas reales, esto se sustituye por inicio de sesión con usuario y contraseña. */

const JP_COOKIE = 'jps';
const JP_DIAS = 30;
/* SHA-256 del código de acceso de la presentación */
const JP_CODIGO_SHA = '92db757b1c3cdac1ab43f854fc60c32330b9a363e79fbf832f62c5738b842a72';

function jp_clave() {
  $f = dirname(__DIR__, 2) . '/jpfutbol-sesion.key';
  $k = is_readable($f) ? trim((string) file_get_contents($f)) : '';
  if (strlen($k) < 64) { $k = bin2hex(random_bytes(32)); if (@file_put_contents($f, $k, LOCK_EX) === false) return ''; @chmod($f, 0600); }
  return $k;
}
function jp_firma($exp, $k) { return hash_hmac('sha256', 'jps|' . $exp, $k); }
function jp_sesion_valida() {
  $c = (string) ($_COOKIE[JP_COOKIE] ?? '');
  if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $c, $m)) return false;
  if ((int) $m[1] < time()) return false;
  $k = jp_clave();
  return $k !== '' && hash_equals(jp_firma($m[1], $k), $m[2]);
}

if (defined('JP_SESION_LIB')) return;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function jp_salir($d, $c = 200) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !preg_match('#^https://(jpfutbolapp|jpfutbol-app)\.(kreavance|kreadigitalstudio)\.es$#', $origen)) jp_salir(['ok' => false, 'error' => 'origen'], 403);
if (!$post) jp_salir(['ok' => jp_sesion_valida()]);

/* Límite de intentos: 8 por IP cada 15 minutos. Se lee, se comprueba y se apunta el intento con el archivo bloqueado,
   antes de mirar el código, para que muchas peticiones a la vez no puedan saltarse el límite. Si acierta, se borra. */
$ahora = time();
$fp = @fopen(sys_get_temp_dir() . '/jpfutbol-ses-' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'jpses'), 0, 24), 'c+');
if (!$fp) jp_salir(['ok' => false, 'error' => 'almacen'], 500);
flock($fp, LOCK_EX);
$fallos = array_values(array_filter(array_map('intval', explode(',', (string) stream_get_contents($fp))), function ($t) use ($ahora) { return $t > $ahora - 900; }));
$bloqueado = count($fallos) >= 8;
if (!$bloqueado) $fallos[] = $ahora;
ftruncate($fp, 0); rewind($fp); fwrite($fp, implode(',', $fallos)); fflush($fp);
flock($fp, LOCK_UN);
if ($bloqueado) { fclose($fp); jp_salir(['ok' => false, 'error' => 'limite'], 429); }

$e = json_decode((string) file_get_contents('php://input'), true);
$codigo = is_array($e) ? strtoupper(trim(substr((string) ($e['codigo'] ?? ''), 0, 40))) : '';
if (!hash_equals(JP_CODIGO_SHA, hash('sha256', $codigo))) { fclose($fp); usleep(400000); jp_salir(['ok' => false, 'error' => 'codigo'], 401); }
flock($fp, LOCK_EX); ftruncate($fp, 0); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
$k = jp_clave();
if ($k === '') jp_salir(['ok' => false, 'error' => 'almacen'], 500);
$exp = (string) ($ahora + JP_DIAS * 86400);
setcookie(JP_COOKIE, $exp . '.' . jp_firma($exp, $k), ['expires' => (int) $exp, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
jp_salir(['ok' => true]);
