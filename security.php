<?php
// security.php
	$env = parse_ini_file(__DIR__ . '/../config.env'); //
	define('ENCRYPTION_KEY', $env['ENCRYPTION_KEY']);
	define('ENCRYPTION_METHOD', 'AES-256-CBC');

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