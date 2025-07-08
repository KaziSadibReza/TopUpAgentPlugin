<?php

namespace TopUpAgent;

use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto as DefuseCrypto;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;

defined('ABSPATH') || exit;

class Crypto
{
    /**
     * The defuse key file name.
     */
    const DEFUSE_FILE = 'defuse.txt';

    /**
     * The secret file name.
     */
    const SECRET_FILE = 'secret.txt';

    /**
     * Folder name inside the wp_contents directory where the cryptographic secrets are stored.
     */
    const PLUGIN_SLUG = 'tua-files';

    /**
     * The defuse key file content.
     *
     * @var string
     */
    private $keyAscii;

    /**
     * The hashing key
     * 
     * @var string
     */
    private $keySecret;

    /**
     * Directory path to the plugin folder inside wp-content/uploads.
     * 
     * @var string
     */
    private $uploads_dir;

    /**
     * Setup Constructor.
     */
    public function __construct()
    {
        $uploads = wp_upload_dir(null, false);

        $this->uploads_dir = $uploads['basedir'] . '/tua-files/';
        $this->setDefuse();
        $this->setSecret();

        add_filter('tua_encrypt', array($this, 'encrypt'), 10, 1);
        add_filter('tua_decrypt', array($this, 'decrypt'), 10, 1);
        add_filter('tua_hash',    array($this, 'hash'),    10, 1);
        add_filter('tua_activation_hash',    array($this, 'activationHash'),    10, 1);

    }

    /**
     * Sets the defuse encryption key.
     */
    private function setDefuse()
    {
        /* When the cryptographic secrets are loaded into these constants, no other files are needed */
        if (defined('TUA_PLUGIN_DEFUSE')) {
            $this->keyAscii = TUA_PLUGIN_DEFUSE;
            error_log('TopUpAgent: Using defuse key from TUA_PLUGIN_DEFUSE constant');
            return;
        }

        $keyFile = $this->uploads_dir . self::DEFUSE_FILE;
        if (file_exists($keyFile)) {
            $this->keyAscii = file_get_contents($keyFile);
            error_log('TopUpAgent: Loaded defuse key from file: ' . $keyFile);
        } else {
            error_log('TopUpAgent: Defuse key file not found: ' . $keyFile);
        }
    }

    /**
     * Sets the cryptographic secret.
     */
    private function setSecret()
    {
        /* When the cryptographic secrets are loaded into these constants, no other files are needed */
        if (defined('TUA_PLUGIN_SECRET')) {
            $this->keySecret = TUA_PLUGIN_SECRET;
            error_log('TopUpAgent: Using secret key from TUA_PLUGIN_SECRET constant');
            return;
        }

        $secretFile = $this->uploads_dir . self::SECRET_FILE;
        if (file_exists($secretFile)) {
            $this->keySecret = file_get_contents($secretFile);
            error_log('TopUpAgent: Loaded secret key from file: ' . $secretFile);
        } else {
            error_log('TopUpAgent: Secret key file not found: ' . $secretFile);
        }
    }

    /**
     * Load the defuse key from the plugin folder.
     *
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     * @throws \Exception
     *
     * @return Key
     */
    private function loadEncryptionKeyFromConfig()
    {
        if (!$this->keyAscii) {
            throw new \Exception('Encryption key not found. Please ensure the plugin is properly activated and cryptographic files are generated.');
        }

        return Key::loadFromAsciiSafeString($this->keyAscii);
    }

    /**
     * Encrypt a string and return the encrypted cipher text.
     *
     * @param string $value
     *
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     * @throws \Exception
     *
     * @return string
     */
    public function encrypt($value)
    {
        if (empty($value)) {
            return '';
        }

        try {
            return DefuseCrypto::encrypt($value, $this->loadEncryptionKeyFromConfig());
        } catch (\Exception $e) {
            error_log('TopUpAgent Crypto Error: ' . $e->getMessage());
            throw new \Exception('Failed to encrypt data: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt a cipher and return the decrypted value.
     *
     * @param string $cipher
     *
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     * @throws \Exception
     *
     * @return string
     */
    public function decrypt($cipher)
    {
        if (!$cipher) {
            return '';
        }

        try {
            return DefuseCrypto::decrypt($cipher, $this->loadEncryptionKeyFromConfig());
        } catch (WrongKeyOrModifiedCiphertextException $ex) {
            // An attack! Either the wrong key was loaded, or the cipher text has changed since it was created -- either
            // corrupted in the database or intentionally modified by someone trying to carry out an attack.
            error_log('TopUpAgent Crypto Security Warning: ' . $ex->getMessage());
            return '';
        } catch (\Exception $e) {
            error_log('TopUpAgent Crypto Error: ' . $e->getMessage());
            throw new \Exception('Failed to decrypt data: ' . $e->getMessage());
        }
    }

    /**
     * Hashes the given string using the HMAC-SHA256 method.
     *
     * @param string $value
     *
     * @return false|string
     */
    public function hash($value)
    {
        return hash_hmac('sha256', $value, $this->keySecret);
    }

    public function activationHash( $license_key ) {
        return sha1( sprintf( '%s%s%s%s', $license_key, tua_rand_hash(), mt_rand( 10000, 1000000 ), tua_clientIp() ) );
    }

}