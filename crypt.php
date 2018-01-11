<?php
/*
 * Quick 'n Dirty Laravel 5.1 decrypter.
 *
 * This is a slightly modified Laravel Crypt encryptor/decryptor script,
 * original by:
 * https://gist.github.com/leonjza/ce27aa7435f8d131d93f
 * Modifications done by Nikolay Dandanov
 *
 * Based directly off the source code at:
 *  https://github.com/laravel/framework/blob/5.1/src/Illuminate/Encryption/Encrypter.php
 *
 * Have access to an application key from a .env?
 * Have some encrypted data you want to decrypt?
 * Well: (new Crypt($key))->decrypt($payload); should have you sorted
 *
 * Have access to an application key from a .env?
 * Want to replace an encrypted value?
 * Well: (new Crypt($key))->encrypt($payload); should have you sorted
 */

//$payload = 'eyJpdiI6Im55MXhqZUkwdW5PYTVycmFFbUVLTnc9PSIsInZhbHVlIjoiQXptOGxcLzVvZVFXUlwvaCtuXC9ad3BqZz09IiwibWFjIjoiMjE2MGU3NjliODVmYWNmNzc1ZjNjY2FjODBkMmIxOWFkZWUxMDE0OWIzNzVjZGIwZDM1NzdiMmY5MmY2NjllNSJ9';
//$key = 'AvDs2rPepOLpWgypXPs04JCFWs4wbDTe';

$payload = $argv[1];
$key = $argv[2];
$newkey = $argv[3];


/*
echo $payload;
echo "
";
echo $key;
echo "
";
echo $newkey;
echo "
";
*/

$decrypted_data = (new Crypt($key))->decrypt($payload);
$reencrypted_data = (new Crypt($newkey))->encrypt($decrypted_data);

// Output
$arr = array('decrypted_data' => $decrypted_data, 'reencrypted_data' => $reencrypted_data);
echo json_encode($arr);


/**
 * Class Crypt
 */
class Crypt
{

    /**
     * The key used to decrypt.
     *
     * @var string
     */
    public $key;

    /**
     * The original encrypted payload
     *
     * @var string
     */
    public $payload;

    /**
     * The cipher types to try for decryption.
     *
     * @var array
     */
    protected $ciphers = [
        'AES-128-CBC',
        'AES-256-CBC'
    ];

    /**
     * Construct a new Crypt
     *
     * @param null $key
     */
    public function __construct($key = null)
    {

        if (!is_null($key))
            $this->key = $key;
    }

    /**
     * Attempt to decrypt a payload.
     *
     * @param $payload
     *
     * @return mixed|string
     * @throws \Exception
     */
    public function decrypt($payload)
    {

        $payload = json_decode(base64_decode($payload), true);

        if ($this->invalidPayload($payload))
            throw new Exception('Invalid Payload Format. Want {iv, value, mac} json.');

        $iv = base64_decode($payload['iv']);

        foreach ($this->ciphers as $cipher) {

            $decrypted = openssl_decrypt($payload['value'],
                $cipher, $this->key, 0, $iv);

            if ($decrypted)
                return unserialize($decrypted);
        }

        return 'Failed to decrypt the payload.';
    }

    /**
     * Encrypt a payload.
     *
     * @param        $payload
     * @param string $cipher
     * @param int    $length
     *
     * @return string
     */
    public function encrypt($payload, $cipher = 'AES-256-CBC', $length = 16)
    {

        $iv = $bytes = openssl_random_pseudo_bytes($length, $strong);
        $value = openssl_encrypt(serialize($payload), $cipher, $this->key, 0, $iv);
        $mac = $this->hmac($iv = base64_encode($iv), $value, $this->key);

        return base64_encode(json_encode(compact('iv', 'value', 'mac')));

    }

    /**
     * Generate a sha256 keyed hash value.
     *
     * @param $iv
     * @param $value
     * @param $key
     *
     * @return string
     */
    public function hmac($iv, $value, $key)
    {

        return hash_hmac('sha256', $iv . $value, $key);
    }

    /**
     * Check if a payload is in the correct format.
     *
     * @param $data
     *
     * @return bool
     */
    public function invalidPayload($data)
    {

        return !is_array($data) || !isset($data['iv']) ||
        !isset($data['value']) || !isset($data['mac']);
    }

}
