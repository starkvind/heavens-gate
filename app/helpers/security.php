<?php
// security.php
include_once(__DIR__ . '/runtime_response.php');

if (!defined('ENCRYPTION_KEY')) {
	$candidates = [
		__DIR__ . '/../../config.env',   // project root
		__DIR__ . '/../../../config.env',// legacy path (if called from deeper)
		__DIR__ . '/../config.env',      // old location
	];
	$env = null;
	foreach ($candidates as $path) {
		if (is_file($path)) {
			$env = parse_ini_file($path);
			break;
		}
	}
	if (!is_array($env) || empty($env['ENCRYPTION_KEY'])) {
		hg_runtime_log_error('security.encryption_key', 'ENCRYPTION_KEY no encontrado en config.env.');
		hg_runtime_bootstrap_error('Error: no se pudo cargar la clave de seguridad.', 500);
		exit;
	}
	define('ENCRYPTION_KEY', $env['ENCRYPTION_KEY']);
}
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');

	/**
	 * Cifra un string de forma reversible
	 */
	function encrypt_string($string) {
		$key = hash('sha256', ENCRYPTION_KEY);
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
		$encrypted = openssl_encrypt($string, ENCRYPTION_METHOD, $key, 0, $iv);
		return base64_encode($iv . '::' . $encrypted);
	}

	/**
	 * Descifra un string previamente cifrado
	 */
	function decrypt_string($encrypted_string) {
		$key = hash('sha256', ENCRYPTION_KEY);
		list($iv, $encrypted) = explode('::', base64_decode($encrypted_string), 2);
		return openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);
	}

?>
