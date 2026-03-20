<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use yii\helpers\String_Helper;
/**
 * Security provides a set of methods to handle common security-related tasks.
 *
 * In particular, Security supports the following features:
 *
 * - Encryption/decryption: [[encryptByKey()]], [[decryptByKey()]], [[encryptByPassword()]] and [[decryptByPassword()]]
 * - Key derivation using standard algorithms: [[pbkdf2()]] and [[hkdf()]]
 * - Data tampering prevention: [[hashData()]] and [[validateData()]]
 * - Password validation: [[generatePasswordHash()]] and [[validatePassword()]]
 *
 * > Note: this class requires 'OpenSSL' PHP extension for random key/string generation on Windows and
 * for encryption/decryption on all platforms. For the highest security level PHP version >= 5.5.0 is recommended.
 *
 * For more details and usage information on Security, see the [guide article on security](guide:security-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Tom Worster <fsb@thefsb.org>
 * @author Klimov Paul <klimov.paul@gmail.com>
 * @since 2.0
 */
class Security extends Component
{
    /**
     * @var string The cipher to use for encryption and decryption.
     */
    public $cipher = 'AES-128-CBC';
    /**
     * @var array[] Look-up table of block sizes and key sizes for each supported OpenSSL cipher.
     *
     * In each element, the key is one of the ciphers supported by OpenSSL (@see openssl_get_cipher_methods()).
     * The value is an array of two integers, the first is the cipher's block size in bytes and the second is
     * the key size in bytes.
     *
     * > Warning: All OpenSSL ciphers that we recommend are in the default value, i.e. AES in CBC mode.
     *
     * > Note: Yii's encryption protocol uses the same size for cipher key, HMAC signature key and key
     * derivation salt.
     */
    public $allowed_ciphers = ['AES-128-CBC' => [16, 16], 'AES-192-CBC' => [16, 24], 'AES-256-CBC' => [16, 32]];
    /**
     * @var string Hash algorithm for key derivation. Recommend sha256, sha384 or sha512.
     * @see [hash_algos()](https://www.php.net/manual/en/function.hash-algos.php)
     */
    public $kdf_hash = 'sha256';
    /**
     * @var string Hash algorithm for message authentication. Recommend sha256, sha384 or sha512.
     * @see [hash_algos()](https://www.php.net/manual/en/function.hash-algos.php)
     */
    public $mac_hash = 'sha256';
    /**
     * @var string HKDF info value for derivation of message authentication key.
     * @see hkdf()
     */
    public $auth_key_info = 'AuthorizationKey';
    /**
     * @var int derivation iterations count.
     * Set as high as possible to hinder dictionary password attacks.
     */
    public $derivation_iterations = 100000;
    /**
     * @var string strategy, which should be used to generate password hash.
     * Available strategies:
     * - 'password_hash' - use of PHP `password_hash()` function with PASSWORD_DEFAULT algorithm.
     *   This option is recommended, but it requires PHP version >= 5.5.0
     * - 'crypt' - use PHP `crypt()` function.
     * @deprecated since version 2.0.7, [[generatePasswordHash()]] ignores [[passwordHashStrategy]] and
     * uses `password_hash()` when available or `crypt()` when not.
     */
    public $password_hash_strategy;
    /**
     * @var int Default cost used for password hashing.
     * Allowed value is between 4 and 31.
     * @see generatePasswordHash()
     * @since 2.0.6
     */
    public $password_hash_cost = 13;
    /**
     * @var boolean if LibreSSL should be used.
     * The recent (> 2.1.5) LibreSSL RNGs are faster and likely better than /dev/urandom.
     */
    private ?bool $_use_libre_ssl = null;
    /**
     * @return bool if LibreSSL should be used
     * Use version is 2.1.5 or higher.
     * @since 2.0.36
     */
    protected function should_use_libre_ssl()
    {
        if ($this->_use_libre_ssl === null) {
            // Parse OPENSSL_VERSION_TEXT because OPENSSL_VERSION_NUMBER is no use for LibreSSL.
            // https://bugs.php.net/bug.php?id=71143
            $this->_use_libre_ssl = defined('OPENSSL_VERSION_TEXT') && preg_match('{^LibreSSL (\d\d?)\.(\d\d?)\.(\d\d?)$}', OPENSSL_VERSION_TEXT, $matches) && 10000 * $matches[1] + 100 * $matches[2] + $matches[3] >= 20105;
        }
        return $this->_use_libre_ssl;
    }
    /**
     * Encrypts data using a password.
     * Derives keys for encryption and authentication from the password using PBKDF2 and a random salt,
     * which is deliberately slow to protect against dictionary attacks. Use [[encryptByKey()]] to
     * encrypt fast using a cryptographic key rather than a password. Key derivation time is
     * determined by [[$derivationIterations]], which should be set as high as possible.
     * The encrypted data includes a keyed message authentication code (MAC) so there is no need
     * to hash input or output data.
     * > Note: Avoid encrypting with passwords wherever possible. Nothing can protect against
     * poor-quality or compromised passwords.
     * @param string $data the data to encrypt
     * @param string $password the password to use for encryption
     * @return string the encrypted data as byte string
     * @see decryptByPassword()
     * @see encryptByKey()
     */
    public function encrypt_by_password($data, $password)
    {
        return $this->encrypt($data, true, $password, null);
    }
    /**
     * Encrypts data using a cryptographic key.
     * Derives keys for encryption and authentication from the input key using HKDF and a random salt,
     * which is very fast relative to [[encryptByPassword()]]. The input key must be properly
     * random -- use [[generateRandomKey()]] to generate keys.
     * The encrypted data includes a keyed message authentication code (MAC) so there is no need
     * to hash input or output data.
     * @param string $data the data to encrypt
     * @param string $inputKey the input to use for encryption and authentication
     * @param string|null $info optional context and application specific information, see [[hkdf()]]
     * @return string the encrypted data as byte string
     * @see decryptByKey()
     * @see encryptByPassword()
     */
    public function encrypt_by_key($data, $input_key, $info = null)
    {
        return $this->encrypt($data, false, $input_key, $info);
    }
    /**
     * Verifies and decrypts data encrypted with [[encryptByPassword()]].
     * @param string $data the encrypted data to decrypt
     * @param string $password the password to use for decryption
     * @return bool|string the decrypted data or false on authentication failure
     * @see encryptByPassword()
     */
    public function decrypt_by_password($data, $password)
    {
        return $this->decrypt($data, true, $password, null);
    }
    /**
     * Verifies and decrypts data encrypted with [[encryptByKey()]].
     * @param string $data the encrypted data to decrypt
     * @param string $inputKey the input to use for encryption and authentication
     * @param string|null $info optional context and application specific information, see [[hkdf()]]
     * @return bool|string the decrypted data or false on authentication failure
     * @see encryptByKey()
     */
    public function decrypt_by_key($data, $input_key, $info = null)
    {
        return $this->decrypt($data, false, $input_key, $info);
    }
    /**
     * Encrypts data.
     *
     * @param string $data data to be encrypted
     * @param bool $passwordBased set true to use password-based key derivation
     * @param string $secret the encryption password or key
     * @param string|null $info context/application specific information, e.g. a user ID
     * See [RFC 5869 Section 3.2](https://tools.ietf.org/html/rfc5869#section-3.2) for more details.
     *
     * @return string the encrypted data as byte string
     * @throws InvalidConfigException on OpenSSL not loaded
     * @throws Exception on OpenSSL error
     * @see decrypt()
     */
    protected function encrypt($data, $password_based, $secret, $info): string
    {
        if (!extension_loaded('openssl')) {
            throw new Invalid_Config_Exception('Encryption requires the OpenSSL PHP extension');
        }
        if (!isset($this->allowed_ciphers[$this->cipher][0], $this->allowed_ciphers[$this->cipher][1])) {
            throw new Invalid_Config_Exception($this->cipher . ' is not an allowed cipher');
        }
        [$block_size, $key_size] = $this->allowed_ciphers[$this->cipher];
        $key_salt = $this->generate_random_key($key_size);
        if ($password_based) {
            $key = $this->pbkdf2($this->kdf_hash, $secret, $key_salt, $this->derivation_iterations, $key_size);
        } else {
            $key = $this->hkdf($this->kdf_hash, $secret, $key_salt, $info, $key_size);
        }
        $iv = $this->generate_random_key($block_size);
        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \yii\base\Exception('OpenSSL failure on encryption: ' . openssl_error_string());
        }
        $auth_key = $this->hkdf($this->kdf_hash, $key, null, $this->auth_key_info, $key_size);
        $hashed = $this->hash_data($iv . $encrypted, $auth_key);
        /*
         * Output: [keySalt][MAC][IV][ciphertext]
         * - keySalt is KEY_SIZE bytes long
         * - MAC: message authentication code, length same as the output of MAC_HASH
         * - IV: initialization vector, length $blockSize
         */
        return $key_salt . $hashed;
    }
    /**
     * Decrypts data.
     *
     * @param string $data encrypted data to be decrypted.
     * @param bool $passwordBased set true to use password-based key derivation
     * @param string $secret the decryption password or key
     * @param string|null $info context/application specific information, @see encrypt()
     *
     * @return bool|string the decrypted data or false on authentication failure
     * @throws InvalidConfigException on OpenSSL not loaded
     * @throws Exception on OpenSSL error
     * @see encrypt()
     */
    protected function decrypt($data, $password_based, $secret, $info)
    {
        if (!extension_loaded('openssl')) {
            throw new Invalid_Config_Exception('Encryption requires the OpenSSL PHP extension');
        }
        if (!isset($this->allowed_ciphers[$this->cipher][0], $this->allowed_ciphers[$this->cipher][1])) {
            throw new Invalid_Config_Exception($this->cipher . ' is not an allowed cipher');
        }
        [$block_size, $key_size] = $this->allowed_ciphers[$this->cipher];
        $key_salt = String_Helper::byte_substr($data, 0, $key_size);
        if ($password_based) {
            $key = $this->pbkdf2($this->kdf_hash, $secret, $key_salt, $this->derivation_iterations, $key_size);
        } else {
            $key = $this->hkdf($this->kdf_hash, $secret, $key_salt, $info, $key_size);
        }
        $auth_key = $this->hkdf($this->kdf_hash, $key, null, $this->auth_key_info, $key_size);
        $data = $this->validate_data(String_Helper::byte_substr($data, $key_size), $auth_key);
        if ($data === false) {
            return false;
        }
        $iv = String_Helper::byte_substr($data, 0, $block_size);
        $encrypted = String_Helper::byte_substr($data, $block_size);
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \yii\base\Exception('OpenSSL failure on decryption: ' . openssl_error_string());
        }
        return $decrypted;
    }
    /**
     * Derives a key from the given input key using the standard HKDF algorithm.
     * Implements HKDF specified in [RFC 5869](https://tools.ietf.org/html/rfc5869).
     * Recommend use one of the SHA-2 hash algorithms: sha224, sha256, sha384 or sha512.
     * @param string $algo a hash algorithm supported by `hash_hmac()`, e.g. 'SHA-256'
     * @param string $inputKey the source key
     * @param string|null $salt the random salt
     * @param string|null $info optional info to bind the derived key material to application-
     * and context-specific information, e.g. a user ID or API version, see
     * [RFC 5869](https://tools.ietf.org/html/rfc5869)
     * @param int $length length of the output key in bytes. If 0, the output key is
     * the length of the hash algorithm output.
     * @throws InvalidArgumentException when HMAC generation fails.
     * @return string the derived key
     */
    public function hkdf(string $algo, $input_key, $salt = null, $info = null, $length = 0)
    {
        if (function_exists('hash_hkdf')) {
            $output_key = hash_hkdf($algo, (string) $input_key, $length, (string) $info, (string) $salt);
            if ($output_key === false) {
                throw new InvalidArgumentException('Invalid parameters to hash_hkdf()');
            }
            return $output_key;
        }
        $test = @hash_hmac($algo, '', '', true);
        if (!$test) {
            throw new InvalidArgumentException('Failed to generate HMAC with hash algorithm: ' . $algo);
        }
        $hash_length = String_Helper::byte_length($test);
        if (is_string($length) && preg_match('{^\d{1,16}$}', $length)) {
            $length = (int) $length;
        }
        if (!is_int($length) || $length < 0 || $length > 255 * $hash_length) {
            throw new InvalidArgumentException('Invalid length');
        }
        $blocks = $length !== 0 ? ceil($length / $hash_length) : 1;
        if ($salt === null) {
            $salt = str_repeat("\x00", $hash_length);
        }
        $pr_key = hash_hmac($algo, $input_key, $salt, true);
        $hmac = '';
        $output_key = '';
        for ($i = 1; $i <= $blocks; $i++) {
            $hmac = hash_hmac($algo, $hmac . $info . chr($i), $pr_key, true);
            $output_key .= $hmac;
        }
        if ($length !== 0) {
            return String_Helper::byte_substr($output_key, 0, $length);
        }
        return $output_key;
    }
    /**
     * Derives a key from the given password using the standard PBKDF2 algorithm.
     * Implements HKDF2 specified in [RFC 2898](https://datatracker.ietf.org/doc/html/rfc2898#section-5.2)
     * Recommend use one of the SHA-2 hash algorithms: sha224, sha256, sha384 or sha512.
     * @param string $algo a hash algorithm supported by `hash_hmac()`, e.g. 'SHA-256'
     * @param string $password the source password
     * @param string $salt the random salt
     * @param int $iterations the number of iterations of the hash algorithm. Set as high as
     * possible to hinder dictionary password attacks.
     * @param int $length length of the output key in bytes. If 0, the output key is
     * the length of the hash algorithm output.
     * @return string the derived key
     * @throws InvalidArgumentException when hash generation fails due to invalid params given.
     */
    public function pbkdf2(string $algo, $password, string $salt, $iterations, $length = 0)
    {
        if (function_exists('hash_pbkdf2') && PHP_VERSION_ID >= 50500) {
            $output_key = hash_pbkdf2($algo, $password, $salt, $iterations, $length, true);
            if ($output_key === false) {
                throw new InvalidArgumentException('Invalid parameters to hash_pbkdf2()');
            }
            return $output_key;
        }
        // todo: is there a nice way to reduce the code repetition in hkdf() and pbkdf2()?
        $test = @hash_hmac($algo, '', '', true);
        if (!$test) {
            throw new InvalidArgumentException('Failed to generate HMAC with hash algorithm: ' . $algo);
        }
        if (is_string($iterations) && preg_match('{^\d{1,16}$}', $iterations)) {
            $iterations = (int) $iterations;
        }
        if (!is_int($iterations) || $iterations < 1) {
            throw new InvalidArgumentException('Invalid iterations');
        }
        if (is_string($length) && preg_match('{^\d{1,16}$}', $length)) {
            $length = (int) $length;
        }
        if (!is_int($length) || $length < 0) {
            throw new InvalidArgumentException('Invalid length');
        }
        $hash_length = String_Helper::byte_length($test);
        $blocks = $length !== 0 ? ceil($length / $hash_length) : 1;
        $output_key = '';
        for ($j = 1; $j <= $blocks; $j++) {
            $hmac = hash_hmac($algo, $salt . pack('N', $j), $password, true);
            $xorsum = $hmac;
            for ($i = 1; $i < $iterations; $i++) {
                $hmac = hash_hmac($algo, $hmac, $password, true);
                $xorsum ^= $hmac;
            }
            $output_key .= $xorsum;
        }
        if ($length !== 0) {
            return String_Helper::byte_substr($output_key, 0, $length);
        }
        return $output_key;
    }
    /**
     * Prefixes data with a keyed hash value so that it can later be detected if it is tampered.
     * There is no need to hash inputs or outputs of [[encryptByKey()]] or [[encryptByPassword()]]
     * as those methods perform the task.
     * @param string $data the data to be protected
     * @param string $key the secret key to be used for generating hash. Should be a secure
     * cryptographic key.
     * @param bool $rawHash whether the generated hash value is in raw binary format. If false, lowercase
     * hex digits will be generated.
     * @return string the data prefixed with the keyed hash
     * @throws InvalidConfigException when HMAC generation fails.
     * @see validateData()
     * @see generateRandomKey()
     * @see hkdf()
     * @see pbkdf2()
     */
    public function hash_data(string $data, string $key, bool $raw_hash = false): string
    {
        $hash = hash_hmac($this->mac_hash, $data, $key, $raw_hash);
        if (!$hash) {
            throw new Invalid_Config_Exception('Failed to generate HMAC with hash algorithm: ' . $this->mac_hash);
        }
        return $hash . $data;
    }
    /**
     * Validates if the given data is tampered.
     * @param string $data the data to be validated. The data must be previously
     * generated by [[hashData()]].
     * @param string $key the secret key that was previously used to generate the hash for the data in [[hashData()]].
     * function to see the supported hashing algorithms on your system. This must be the same
     * as the value passed to [[hashData()]] when generating the hash for the data.
     * @param bool $rawHash this should take the same value as when you generate the data using [[hashData()]].
     * It indicates whether the hash value in the data is in binary format. If false, it means the hash value consists
     * of lowercase hex digits only.
     * hex digits will be generated.
     * @return string|false the real data with the hash stripped off. False if the data is tampered.
     * @throws InvalidConfigException when HMAC generation fails.
     * @see hashData()
     */
    public function validate_data(string $data, string $key, bool $raw_hash = false): string|false
    {
        $test = @hash_hmac($this->mac_hash, '', '', $raw_hash);
        if (!$test) {
            throw new Invalid_Config_Exception('Failed to generate HMAC with hash algorithm: ' . $this->mac_hash);
        }
        $hash_length = String_Helper::byte_length($test);
        if (String_Helper::byte_length($data) >= $hash_length) {
            $hash = String_Helper::byte_substr($data, 0, $hash_length);
            $pure_data = String_Helper::byte_substr($data, $hash_length);
            $calculated_hash = hash_hmac($this->mac_hash, $pure_data, $key, $raw_hash);
            if ($this->compare_string($hash, $calculated_hash)) {
                return $pure_data;
            }
        }
        return false;
    }
    /**
     * Generates specified number of random bytes.
     * Note that output may not be ASCII.
     * @see generateRandomString() if you need a string.
     *
     * @param int $length the number of bytes to generate
     * @return string the generated random bytes
     * @throws InvalidArgumentException if wrong length is specified
     * @throws Exception on failure.
     */
    public function generate_random_key($length = 32): string
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('First parameter ($length) must be an integer');
        }
        if ($length < 1) {
            throw new InvalidArgumentException('First parameter ($length) must be greater than 0');
        }
        return random_bytes($length);
    }
    /**
     * Generates a random string of specified length.
     * The string generated matches [A-Za-z0-9_-]+ and is transparent to URL-encoding.
     *
     * @param int $length the length of the key in characters
     * @return string the generated random key
     * @throws Exception on failure.
     */
    public function generate_random_string($length = 32): string
    {
        if (!is_int($length)) {
            throw new InvalidArgumentException('First parameter ($length) must be an integer');
        }
        if ($length < 1) {
            throw new InvalidArgumentException('First parameter ($length) must be greater than 0');
        }
        $bytes = $this->generate_random_key($length);
        return substr(String_Helper::base64url_encode($bytes), 0, $length);
    }
    /**
     * Generates a secure hash from a password and a random salt.
     *
     * The generated hash can be stored in database.
     * Later when a password needs to be validated, the hash can be fetched and passed
     * to [[validatePassword()]]. For example,
     *
     * ```
     * // generates the hash (usually done during user registration or when the password is changed)
     * $hash = Yii::$app->getSecurity()->generatePasswordHash($password);
     * // ...save $hash in database...
     *
     * // during login, validate if the password entered is correct using $hash fetched from database
     * if (Yii::$app->getSecurity()->validatePassword($password, $hash)) {
     *     // password is good
     * } else {
     *     // password is bad
     * }
     * ```
     *
     * @param string $password The password to be hashed.
     * @param int|null $cost Cost parameter used by the Blowfish hash algorithm.
     * The higher the value of cost,
     * the longer it takes to generate the hash and to verify a password against it. Higher cost
     * therefore slows down a brute-force attack. For best protection against brute-force attacks,
     * set it to the highest value that is tolerable on production servers. The time taken to
     * compute the hash doubles for every increment by one of $cost.
     * @return string The password hash string. When [[passwordHashStrategy]] is set to 'crypt',
     * the output is always 60 ASCII characters, when set to 'password_hash' the output length
     * might increase in future versions of PHP (https://www.php.net/manual/en/function.password-hash.php)
     * @throws Exception on bad password parameter or cost parameter.
     * @see validatePassword()
     */
    public function generate_password_hash(string $password, ?int $cost = null): string
    {
        if ($cost === null) {
            $cost = $this->password_hash_cost;
        }
        if (function_exists('password_hash')) {
            /* @noinspection PhpUndefinedConstantInspection */
            return password_hash($password, PASSWORD_DEFAULT, ['cost' => $cost]);
        }
        $salt = $this->generate_salt($cost);
        $hash = crypt($password, $salt);
        // strlen() is safe since crypt() returns only ascii
        if (strlen($hash) !== 60) {
            throw new Exception('Unknown error occurred while generating hash.');
        }
        return $hash;
    }
    /**
     * Verifies a password against a hash.
     * @param string $password The password to verify.
     * @param string $hash The hash to verify the password against.
     * @return bool whether the password is correct.
     * @throws InvalidArgumentException on bad password/hash parameters or if crypt() with Blowfish hash is not available.
     * @see generatePasswordHash()
     */
    public function validate_password($password, $hash)
    {
        if (!is_string($password) || $password === '') {
            throw new InvalidArgumentException('Password must be a string and cannot be empty.');
        }
        if (!preg_match('/^\$2[axy]\$(\d\d)\$[\.\/0-9A-Za-z]{22}/', $hash, $matches) || $matches[1] < 4 || $matches[1] > 30) {
            throw new InvalidArgumentException('Hash is invalid.');
        }
        if (function_exists('password_verify')) {
            return password_verify($password, $hash);
        }
        $test = crypt($password, $hash);
        $n = strlen($test);
        if ($n !== 60) {
            return false;
        }
        return $this->compare_string($test, $hash);
    }
    /**
     * Generates a salt that can be used to generate a password hash.
     *
     * The PHP [crypt()](https://www.php.net/manual/en/function.crypt.php) built-in function
     * requires, for the Blowfish hash algorithm, a salt string in a specific format:
     * "$2a$", "$2x$" or "$2y$", a two digit cost parameter, "$", and 22 characters
     * from the alphabet "./0-9A-Za-z".
     *
     * @param int $cost the cost parameter
     * @return string the random salt value.
     * @throws InvalidArgumentException if the cost parameter is out of the range of 4 to 31.
     */
    protected function generate_salt($cost = 13)
    {
        $cost = (int) $cost;
        if ($cost < 4 || $cost > 31) {
            throw new InvalidArgumentException('Cost must be between 4 and 31.');
        }
        // Get a 20-byte random string
        $rand = $this->generate_random_key(20);
        // Form the prefix that specifies Blowfish (bcrypt) algorithm and cost parameter.
        $salt = sprintf('$2y$%02d$', $cost);
        // Append the random salt data in the required base64 format.
        $salt .= str_replace('+', '.', substr(base64_encode($rand), 0, 22));
        return $salt;
    }
    /**
     * Performs string comparison using a timing-attack-resistant approach.
     *
     * Delegates to PHP's native `hash_equals()` when available (PHP >= 5.6),
     * otherwise falls back to a bitwise XOR loop that runs in constant time
     * relative to the length of `$expected` regardless of where strings diverge.
     *
     * **Security note**: never use `===` or `==` to compare security-sensitive
     * strings such as HMAC signatures, CSRF tokens or password reset tokens —
     * those operators short-circuit as soon as a differing byte is found, leaking
     * timing information that can be exploited to guess the correct value byte-by-byte.
     *
     * @param string $expected The trusted reference value (e.g. server-computed HMAC).
     * @param string $actual The untrusted user-supplied value to compare against.
     * @return bool `true` if both strings are byte-for-byte identical; `false` otherwise.
     * @throws \yii\base\InvalidArgumentException When either argument is not a string.
     * @complexity O(n) where n = strlen($expected) — always runs to completion.
     * @see https://codereview.stackexchange.com/q/13512
     * @see https://www.php.net/manual/en/function.hash-equals.php
     * @since 2.0
     */
    public function compare_string(string $expected, string $actual): bool
    {
        if (!is_string($expected)) {
            throw new InvalidArgumentException('Expected expected value to be a string, ' . gettype($expected) . ' given.');
        }
        if (!is_string($actual)) {
            throw new InvalidArgumentException('Expected actual value to be a string, ' . gettype($actual) . ' given.');
        }
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }
        $expected .= "\x00";
        $actual .= "\x00";
        $expected_length = String_Helper::byte_length($expected);
        $actual_length = String_Helper::byte_length($actual);
        $diff = $expected_length - $actual_length;
        for ($i = 0; $i < $actual_length; $i++) {
            $diff |= ord($actual[$i]) ^ ord($expected[$i % $expected_length]);
        }
        return $diff === 0;
    }
    /**
     * Masks a token to make it uncompressible.
     * Applies a random mask to the token and prepends the mask used to the result making the string always unique.
     * Used to mitigate BREACH attack by randomizing how token is outputted on each request.
     * @param string $token An unmasked token.
     * @return string A masked token.
     * @since 2.0.12
     */
    public function mask_token($token)
    {
        // The number of bytes in a mask is always equal to the number of bytes in a token.
        $mask = $this->generate_random_key(String_Helper::byte_length($token));
        return String_Helper::base64url_encode($mask . ($mask ^ $token));
    }
    /**
     * Unmasks a token previously masked by `maskToken`.
     * @param string $maskedToken A masked token.
     * @return string An unmasked token, or an empty string in case of token format is invalid.
     * @since 2.0.12
     */
    public function unmask_token($masked_token)
    {
        $decoded = String_Helper::base64url_decode($masked_token);
        $length = String_Helper::byte_length($decoded) / 2;
        // Check if the masked token has an even length.
        if (!is_int($length)) {
            return '';
        }
        return String_Helper::byte_substr($decoded, $length, $length) ^ String_Helper::byte_substr($decoded, 0, $length);
    }
}