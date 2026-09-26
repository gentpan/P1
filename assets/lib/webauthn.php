<?php
/**
 * lbuchs/WebAuthn v2.2.0 (20adb4a240c3997bd8cac7dc4dde38ab0bea0ed1).
 * Bundled none-attestation subset; namespace isolated for P1; source includes removed.
 * https://github.com/lbuchs/WebAuthn
 *
 * MIT License
 * 
 * Copyright © 2022 Lukas Buchs
 * Copyright © 2018 Thomas Bleeker (CBOR & ByteBuffer part)
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/* Upstream: WebAuthnException.php */

namespace P1\WebAuthn;

/**
 * @author Lukas Buchs
 * @license https://github.com/lbuchs/WebAuthn/blob/master/LICENSE MIT
 */
class WebAuthnException extends \Exception {
    const INVALID_DATA = 1;
    const INVALID_TYPE = 2;
    const INVALID_CHALLENGE = 3;
    const INVALID_ORIGIN = 4;
    const INVALID_RELYING_PARTY = 5;
    const INVALID_SIGNATURE = 6;
    const INVALID_PUBLIC_KEY = 7;
    const CERTIFICATE_NOT_TRUSTED = 8;
    const USER_PRESENT = 9;
    const USER_VERIFICATED = 10;
    const SIGNATURE_COUNTER = 11;
    const CRYPTO_STRONG = 13;
    const BYTEBUFFER = 14;
    const CBOR = 15;
    const ANDROID_NOT_TRUSTED = 16;

    public function __construct($message = "", $code = 0, $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

/* Upstream: Binary/ByteBuffer.php */



namespace P1\WebAuthn\Binary;
use P1\WebAuthn\WebAuthnException;

/**
 * Modified version of https://github.com/madwizard-thomas/webauthn-server/blob/master/src/Format/ByteBuffer.php
 * Copyright © 2018 Thomas Bleeker - MIT licensed
 * Modified by Lukas Buchs
 * Thanks Thomas for your work!
 */
class ByteBuffer implements \JsonSerializable, \Serializable {
    /**
     * @var bool
     */
    public static $useBase64UrlEncoding = false;

    /**
     * @var string
     */
    private $_data;

    /**
     * @var int
     */
    private $_length;

    public function __construct($binaryData) {
        $this->_data = (string)$binaryData;
        $this->_length = \strlen($binaryData);
    }


    // -----------------------
    // PUBLIC STATIC
    // -----------------------

    /**
     * create a ByteBuffer from a base64 url encoded string
     * @param string $base64url
     * @return ByteBuffer
     */
    public static function fromBase64Url($base64url): ByteBuffer {
        $bin = self::_base64url_decode($base64url);
        if ($bin === false) {
            throw new WebAuthnException('ByteBuffer: Invalid base64 url string', WebAuthnException::BYTEBUFFER);
        }
        return new ByteBuffer($bin);
    }

    /**
     * create a ByteBuffer from a base64 url encoded string
     * @param string $hex
     * @return ByteBuffer
     */
    public static function fromHex($hex): ByteBuffer {
        $bin = \hex2bin($hex);
        if ($bin === false) {
            throw new WebAuthnException('ByteBuffer: Invalid hex string', WebAuthnException::BYTEBUFFER);
        }
        return new ByteBuffer($bin);
    }

    /**
     * create a random ByteBuffer
     * @param string $length
     * @return ByteBuffer
     */
    public static function randomBuffer($length): ByteBuffer {
        if (\function_exists('random_bytes')) { // >PHP 7.0
            return new ByteBuffer(\random_bytes($length));

        } else if (\function_exists('openssl_random_pseudo_bytes')) {
            return new ByteBuffer(\openssl_random_pseudo_bytes($length));

        } else {
            throw new WebAuthnException('ByteBuffer: cannot generate random bytes', WebAuthnException::BYTEBUFFER);
        }
    }

    // -----------------------
    // PUBLIC
    // -----------------------

    public function getBytes($offset, $length): string {
        if ($offset < 0 || $length < 0 || ($offset + $length > $this->_length)) {
            throw new WebAuthnException('ByteBuffer: Invalid offset or length', WebAuthnException::BYTEBUFFER);
        }
        return \substr($this->_data, $offset, $length);
    }

    public function getByteVal($offset): int {
        if ($offset < 0 || $offset >= $this->_length) {
            throw new WebAuthnException('ByteBuffer: Invalid offset', WebAuthnException::BYTEBUFFER);
        }
        return \ord(\substr($this->_data, $offset, 1));
    }

    public function getJson($jsonFlags=0) {
        $data = \json_decode($this->getBinaryString(), null, 512, $jsonFlags);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new WebAuthnException(\json_last_error_msg(), WebAuthnException::BYTEBUFFER);
        }
        return $data;
    }

    public function getLength(): int {
        return $this->_length;
    }

    public function getUint16Val($offset) {
        if ($offset < 0 || ($offset + 2) > $this->_length) {
            throw new WebAuthnException('ByteBuffer: Invalid offset', WebAuthnException::BYTEBUFFER);
        }
        return unpack('n', $this->_data, $offset)[1];
    }

    public function getUint32Val($offset) {
        if ($offset < 0 || ($offset + 4) > $this->_length) {
            throw new WebAuthnException('ByteBuffer: Invalid offset', WebAuthnException::BYTEBUFFER);
        }
        $val = unpack('N', $this->_data, $offset)[1];

        // Signed integer overflow causes signed negative numbers
        if ($val < 0) {
            throw new WebAuthnException('ByteBuffer: Value out of integer range.', WebAuthnException::BYTEBUFFER);
        }
        return $val;
    }

    public function getUint64Val($offset) {
        if (PHP_INT_SIZE < 8) {
            throw new WebAuthnException('ByteBuffer: 64-bit values not supported by this system', WebAuthnException::BYTEBUFFER);
        }
        if ($offset < 0 || ($offset + 8) > $this->_length) {
            throw new WebAuthnException('ByteBuffer: Invalid offset', WebAuthnException::BYTEBUFFER);
        }
        $val = unpack('J', $this->_data, $offset)[1];

        // Signed integer overflow causes signed negative numbers
        if ($val < 0) {
            throw new WebAuthnException('ByteBuffer: Value out of integer range.', WebAuthnException::BYTEBUFFER);
        }

        return $val;
    }

    public function getHalfFloatVal($offset) {
        //FROM spec pseudo decode_half(unsigned char *halfp)
        $half = $this->getUint16Val($offset);

        $exp = ($half >> 10) & 0x1f;
        $mant = $half & 0x3ff;

        if ($exp === 0) {
            $val = $mant * (2 ** -24);
        } elseif ($exp !== 31) {
            $val = ($mant + 1024) * (2 ** ($exp - 25));
        } else {
            $val = ($mant === 0) ? INF : NAN;
        }

        return ($half & 0x8000) ? -$val : $val;
    }

    public function getFloatVal($offset) {
        if ($offset < 0 || ($offset + 4) > $this->_length) {
            throw new WebAuthnException('ByteBuffer: Invalid offset', WebAuthnException::BYTEBUFFER);
        }
        return unpack('G', $this->_data, $offset)[1];
    }

    public function getDoubleVal($offset) {
        if ($offset < 0 || ($offset + 8) > $this->_length) {
            throw new WebAuthnException('ByteBuffer: Invalid offset', WebAuthnException::BYTEBUFFER);
        }
        return unpack('E', $this->_data, $offset)[1];
    }

    /**
     * @return string
     */
    public function getBinaryString(): string {
        return $this->_data;
    }

    /**
     * @param string|ByteBuffer $buffer
     * @return bool
     */
    public function equals($buffer): bool {
        if (is_object($buffer) && $buffer instanceof ByteBuffer) {
            return $buffer->getBinaryString() === $this->getBinaryString();

        } else if (is_string($buffer)) {
            return $buffer === $this->getBinaryString();
        }
        
        return false;
    }

    /**
     * @return string
     */
    public function getHex(): string {
        return \bin2hex($this->_data);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->_length === 0;
    }


    /**
     * jsonSerialize interface
     * return binary data in RFC 1342-Like serialized string
     * @return string
     */
    public function jsonSerialize(): string {
        if (ByteBuffer::$useBase64UrlEncoding) {
            return self::_base64url_encode($this->_data);

        } else {
            return '=?BINARY?B?' . \base64_encode($this->_data) . '?=';
        }
    }

    /**
     * Serializable-Interface
     * @return string
     */
    public function serialize(): string {
        return \serialize($this->_data);
    }

    /**
     * Serializable-Interface
     * @param string $serialized
     */
    public function unserialize($serialized) {
        $this->_data = \unserialize($serialized);
        $this->_length = \strlen($this->_data);
    }

    /**
     * (PHP 8 deprecates Serializable-Interface)
     * @return array
     */
    public function __serialize(): array {
        return [
            'data' => \serialize($this->_data)
        ];
    }

    /**
     * object to string
     * @return string
     */
    public function __toString(): string {
        return $this->getHex();
    }

    /**
     * (PHP 8 deprecates Serializable-Interface)
     * @param array $data
     * @return void
     */
    public function __unserialize($data) {
        if ($data && isset($data['data'])) {
            $this->_data = \unserialize($data['data']);
            $this->_length = \strlen($this->_data);
        }
    }

    // -----------------------
    // PROTECTED STATIC
    // -----------------------

    /**
     * base64 url decoding
     * @param string $data
     * @return string
     */
    protected static function _base64url_decode($data): string {
        return \base64_decode(\strtr($data, '-_', '+/') . \str_repeat('=', 3 - (3 + \strlen($data)) % 4));
    }

    /**
     * base64 url encoding
     * @param string $data
     * @return string
     */
    protected static function _base64url_encode($data): string {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }
}

/* Upstream: CBOR/CborDecoder.php */



namespace P1\WebAuthn\CBOR;
use P1\WebAuthn\WebAuthnException;
use P1\WebAuthn\Binary\ByteBuffer;

/**
 * Modified version of https://github.com/madwizard-thomas/webauthn-server/blob/master/src/Format/CborDecoder.php
 * Copyright © 2018 Thomas Bleeker - MIT licensed
 * Modified by Lukas Buchs
 * Thanks Thomas for your work!
 */
class CborDecoder {
    const CBOR_MAJOR_UNSIGNED_INT = 0;
    const CBOR_MAJOR_TEXT_STRING = 3;
    const CBOR_MAJOR_FLOAT_SIMPLE = 7;
    const CBOR_MAJOR_NEGATIVE_INT = 1;
    const CBOR_MAJOR_ARRAY = 4;
    const CBOR_MAJOR_TAG = 6;
    const CBOR_MAJOR_MAP = 5;
    const CBOR_MAJOR_BYTE_STRING = 2;

    /**
     * @param ByteBuffer|string $bufOrBin
     * @return mixed
     * @throws WebAuthnException
     */
    public static function decode($bufOrBin) {
        $buf = $bufOrBin instanceof ByteBuffer ? $bufOrBin : new ByteBuffer($bufOrBin);

        $offset = 0;
        $result = self::_parseItem($buf, $offset);
        if ($offset !== $buf->getLength()) {
            throw new WebAuthnException('Unused bytes after data item.', WebAuthnException::CBOR);
        }
        return $result;
    }

    /**
     * @param ByteBuffer|string $bufOrBin
     * @param int $startOffset
     * @param int|null $endOffset
     * @return mixed
     */
    public static function decodeInPlace($bufOrBin, $startOffset, &$endOffset = null) {
        $buf = $bufOrBin instanceof ByteBuffer ? $bufOrBin : new ByteBuffer($bufOrBin);

        $offset = $startOffset;
        $data = self::_parseItem($buf, $offset);
        $endOffset = $offset;
        return $data;
    }

    // ---------------------
    // protected
    // ---------------------

    /**
     * @param ByteBuffer $buf
     * @param int $offset
     * @return mixed
     */
    protected static function _parseItem(ByteBuffer $buf, &$offset) {
        $first = $buf->getByteVal($offset++);
        $type = $first >> 5;
        $val = $first & 0b11111;

        if ($type === self::CBOR_MAJOR_FLOAT_SIMPLE) {
            return self::_parseFloatSimple($val, $buf, $offset);
        }

        $val = self::_parseExtraLength($val, $buf, $offset);

        return self::_parseItemData($type, $val, $buf, $offset);
    }

    protected static function _parseFloatSimple($val, ByteBuffer $buf, &$offset) {
        switch ($val) {
            case 24:
                $val = $buf->getByteVal($offset);
                $offset++;
                return self::_parseSimple($val);

            case 25:
                $floatValue = $buf->getHalfFloatVal($offset);
                $offset += 2;
                return $floatValue;

            case 26:
                $floatValue = $buf->getFloatVal($offset);
                $offset += 4;
                return $floatValue;

            case 27:
                $floatValue = $buf->getDoubleVal($offset);
                $offset += 8;
                return $floatValue;

            case 28:
            case 29:
            case 30:
                throw new WebAuthnException('Reserved value used.', WebAuthnException::CBOR);

            case 31:
                throw new WebAuthnException('Indefinite length is not supported.', WebAuthnException::CBOR);
        }

        return self::_parseSimple($val);
    }

    /**
     * @param int $val
     * @return mixed
     * @throws WebAuthnException
     */
    protected static function _parseSimple($val) {
        if ($val === 20) {
            return false;
        }
        if ($val === 21) {
            return true;
        }
        if ($val === 22) {
            return null;
        }
        throw new WebAuthnException(sprintf('Unsupported simple value %d.', $val), WebAuthnException::CBOR);
    }

    protected static function _parseExtraLength($val, ByteBuffer $buf, &$offset) {
        switch ($val) {
            case 24:
                $val = $buf->getByteVal($offset);
                $offset++;
                break;

            case 25:
                $val = $buf->getUint16Val($offset);
                $offset += 2;
                break;

            case 26:
                $val = $buf->getUint32Val($offset);
                $offset += 4;
                break;

            case 27:
                $val = $buf->getUint64Val($offset);
                $offset += 8;
                break;

            case 28:
            case 29:
            case 30:
                throw new WebAuthnException('Reserved value used.', WebAuthnException::CBOR);

            case 31:
                throw new WebAuthnException('Indefinite length is not supported.', WebAuthnException::CBOR);
        }

        return $val;
    }

    protected static function _parseItemData($type, $val, ByteBuffer $buf, &$offset) {
        switch ($type) {
            case self::CBOR_MAJOR_UNSIGNED_INT: // uint
                return $val;

            case self::CBOR_MAJOR_NEGATIVE_INT:
                return -1 - $val;

            case self::CBOR_MAJOR_BYTE_STRING:
                $data = $buf->getBytes($offset, $val);
                $offset += $val;
                return new ByteBuffer($data); // bytes

            case self::CBOR_MAJOR_TEXT_STRING:
                $data = $buf->getBytes($offset, $val);
                $offset += $val;
                return $data; // UTF-8

            case self::CBOR_MAJOR_ARRAY:
                return self::_parseArray($buf, $offset, $val);

            case self::CBOR_MAJOR_MAP:
                return self::_parseMap($buf, $offset, $val);

            case self::CBOR_MAJOR_TAG:
                return self::_parseItem($buf, $offset); // 1 embedded data item
        }

        // This should never be reached
        throw new WebAuthnException(sprintf('Unknown major type %d.', $type), WebAuthnException::CBOR);
    }

    protected static function _parseMap(ByteBuffer $buf, &$offset, $count) {
        $map = array();

        for ($i = 0; $i < $count; $i++) {
            $mapKey = self::_parseItem($buf, $offset);
            $mapVal = self::_parseItem($buf, $offset);

            if (!\is_int($mapKey) && !\is_string($mapKey)) {
                throw new WebAuthnException('Can only use strings or integers as map keys', WebAuthnException::CBOR);
            }

            $map[$mapKey] = $mapVal; // todo dup
        }
        return $map;
    }

    protected static function _parseArray(ByteBuffer $buf, &$offset, $count) {
        $arr = array();
        for ($i = 0; $i < $count; $i++) {
            $arr[] = self::_parseItem($buf, $offset);
        }

        return $arr;
    }
}

/* Upstream: Attestation/AuthenticatorData.php */


namespace P1\WebAuthn\Attestation;
use P1\WebAuthn\WebAuthnException;
use P1\WebAuthn\CBOR\CborDecoder;
use P1\WebAuthn\Binary\ByteBuffer;

/**
 * @author Lukas Buchs
 * @license https://github.com/lbuchs/WebAuthn/blob/master/LICENSE MIT
 */
class AuthenticatorData {
    protected $_binary;
    protected $_rpIdHash;
    protected $_flags;
    protected $_signCount;
    protected $_attestedCredentialData;
    protected $_extensionData;



    // Cose encoded keys
    private static $_COSE_KTY = 1;
    private static $_COSE_ALG = 3;

    // Cose curve
    private static $_COSE_CRV = -1;
    private static $_COSE_X = -2;
    private static $_COSE_Y = -3;

    // Cose RSA PS256
    private static $_COSE_N = -1;
    private static $_COSE_E = -2;

    // EC2 key type
    private static $_EC2_TYPE = 2;
    private static $_EC2_ES256 = -7;
    private static $_EC2_P256 = 1;

    // RSA key type
    private static $_RSA_TYPE = 3;
    private static $_RSA_RS256 = -257;

    // OKP key type
    private static $_OKP_TYPE = 1;
    private static $_OKP_ED25519 = 6;
    private static $_OKP_EDDSA = -8;

    /**
     * Parsing the authenticatorData binary.
     * @param string $binary
     * @throws WebAuthnException
     */
    public function __construct($binary) {
        if (!\is_string($binary) || \strlen($binary) < 37) {
            throw new WebAuthnException('Invalid authenticatorData input', WebAuthnException::INVALID_DATA);
        }
        $this->_binary = $binary;

        // Read infos from binary
        // https://www.w3.org/TR/webauthn/#sec-authenticator-data

        // RP ID
        $this->_rpIdHash = \substr($binary, 0, 32);

        // flags (1 byte)
        $flags = \unpack('Cflags', \substr($binary, 32, 1))['flags'];
        $this->_flags = $this->_readFlags($flags);

        // signature counter: 32-bit unsigned big-endian integer.
        $this->_signCount = \unpack('Nsigncount', \substr($binary, 33, 4))['signcount'];

        $offset = 37;
        // https://www.w3.org/TR/webauthn/#sec-attested-credential-data
        if ($this->_flags->attestedDataIncluded) {
            $this->_attestedCredentialData = $this->_readAttestData($binary, $offset);
        }

        if ($this->_flags->extensionDataIncluded) {
            $this->_readExtensionData(\substr($binary, $offset));
        }
    }

    /**
     * Authenticator Attestation Globally Unique Identifier, a unique number
     * that identifies the model of the authenticator (not the specific instance
     * of the authenticator)
     * The aaguid may be 0 if the user is using a old u2f device and/or if
     * the browser is using the fido-u2f format.
     * @return string
     * @throws WebAuthnException
     */
    public function getAAGUID() {
        if (!($this->_attestedCredentialData instanceof \stdClass)) {
            throw  new WebAuthnException('credential data not included in authenticator data', WebAuthnException::INVALID_DATA);
        }
        return $this->_attestedCredentialData->aaguid;
    }

    /**
     * returns the authenticatorData as binary
     * @return string
     */
    public function getBinary() {
        return $this->_binary;
    }

    /**
     * returns the credentialId
     * @return string
     * @throws WebAuthnException
     */
    public function getCredentialId() {
        if (!($this->_attestedCredentialData instanceof \stdClass)) {
            throw  new WebAuthnException('credential id not included in authenticator data', WebAuthnException::INVALID_DATA);
        }
        return $this->_attestedCredentialData->credentialId;
    }

    /**
     * returns the public key in PEM format
     * @return string
     */
    public function getPublicKeyPem() {
        if (!($this->_attestedCredentialData instanceof \stdClass) || !isset($this->_attestedCredentialData->credentialPublicKey)) {
            throw  new WebAuthnException('credential data not included in authenticator data', WebAuthnException::INVALID_DATA);
        }
        
        $der = null;
        switch ($this->_attestedCredentialData->credentialPublicKey->kty ?? null) {
            case self::$_EC2_TYPE: $der = $this->_getEc2Der(); break;
            case self::$_RSA_TYPE: $der = $this->_getRsaDer(); break;
            case self::$_OKP_TYPE: $der = $this->_getOkpDer(); break;
            default: throw new WebAuthnException('invalid key type', WebAuthnException::INVALID_DATA);
        }

        $pem = '-----BEGIN PUBLIC KEY-----' . "\n";
        $pem .= \chunk_split(\base64_encode($der), 64, "\n");
        $pem .= '-----END PUBLIC KEY-----' . "\n";
        return $pem;
    }

    /**
     * returns the public key in U2F format
     * @return string
     * @throws WebAuthnException
     */
    public function getPublicKeyU2F() {
        if (!($this->_attestedCredentialData instanceof \stdClass) || !isset($this->_attestedCredentialData->credentialPublicKey)) {
            throw  new WebAuthnException('credential data not included in authenticator data', WebAuthnException::INVALID_DATA);
        }
        if (($this->_attestedCredentialData->credentialPublicKey->kty ?? null) !== self::$_EC2_TYPE) {
            throw new WebAuthnException('signature algorithm not ES256', WebAuthnException::INVALID_PUBLIC_KEY);
        }
        return "\x04" . // ECC uncompressed
                $this->_attestedCredentialData->credentialPublicKey->x .
                $this->_attestedCredentialData->credentialPublicKey->y;
    }

    /**
     * returns the SHA256 hash of the relying party id (=hostname)
     * @return string
     */
    public function getRpIdHash() {
        return $this->_rpIdHash;
    }

    /**
     * returns the sign counter
     * @return int
     */
    public function getSignCount() {
        return $this->_signCount;
    }

    /**
     * returns true if the user is present
     * @return boolean
     */
    public function getUserPresent() {
        return $this->_flags->userPresent;
    }

    /**
     * returns true if the user is verified
     * @return boolean
     */
    public function getUserVerified() {
        return $this->_flags->userVerified;
    }

    /**
     * returns true if the backup is eligible
     * @return boolean
     */
    public function getIsBackupEligible()
    {
        return $this->_flags->isBackupEligible;
    }

    /**
     * returns true if the current credential is backed up
     * @return boolean
     */
    public function getIsBackup()
    {
        return $this->_flags->isBackup;
    }

    // -----------------------------------------------
    // PRIVATE
    // -----------------------------------------------

    /**
     * Returns DER encoded EC2 key
     * @return string
     */
    private function _getEc2Der() {
        return $this->_der_sequence(
            $this->_der_sequence(
                $this->_der_oid("\x2A\x86\x48\xCE\x3D\x02\x01") . // OID 1.2.840.10045.2.1 ecPublicKey
                $this->_der_oid("\x2A\x86\x48\xCE\x3D\x03\x01\x07")  // 1.2.840.10045.3.1.7 prime256v1
            ) .
            $this->_der_bitString($this->getPublicKeyU2F())
        );
    }

    /**
     * Returns DER encoded EdDSA key
     * @return string
     */
    private function _getOkpDer() {
        return $this->_der_sequence(
            $this->_der_sequence(
                $this->_der_oid("\x2B\x65\x70") // OID 1.3.101.112 curveEd25519 (EdDSA 25519 signature algorithm)
            ) .
            $this->_der_bitString($this->_attestedCredentialData->credentialPublicKey->x)
        );
    }

    /**
     * Returns DER encoded RSA key
     * @return string
     */
    private function _getRsaDer() {
        return $this->_der_sequence(
            $this->_der_sequence(
                $this->_der_oid("\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01") . // OID 1.2.840.113549.1.1.1 rsaEncryption
                $this->_der_nullValue()
            ) .
            $this->_der_bitString(
                $this->_der_sequence(
                    $this->_der_unsignedInteger($this->_attestedCredentialData->credentialPublicKey->n) .
                    $this->_der_unsignedInteger($this->_attestedCredentialData->credentialPublicKey->e)
                )
            )
        );
    }

    /**
     * reads the flags from flag byte
     * @param string $binFlag
     * @return \stdClass
     */
    private function _readFlags($binFlag) {
        $flags = new \stdClass();

        $flags->bit_0 = !!($binFlag & 1);
        $flags->bit_1 = !!($binFlag & 2);
        $flags->bit_2 = !!($binFlag & 4);
        $flags->bit_3 = !!($binFlag & 8);
        $flags->bit_4 = !!($binFlag & 16);
        $flags->bit_5 = !!($binFlag & 32);
        $flags->bit_6 = !!($binFlag & 64);
        $flags->bit_7 = !!($binFlag & 128);

        // named flags
        $flags->userPresent = $flags->bit_0;
        $flags->userVerified = $flags->bit_2;
        $flags->isBackupEligible = $flags->bit_3;
        $flags->isBackup = $flags->bit_4;
        $flags->attestedDataIncluded = $flags->bit_6;
        $flags->extensionDataIncluded = $flags->bit_7;
        return $flags;
    }

    /**
     * read attested data
     * @param string $binary
     * @param int $endOffset
     * @return \stdClass
     * @throws WebAuthnException
     */
    private function _readAttestData($binary, &$endOffset) {
        $attestedCData = new \stdClass();
        if (\strlen($binary) <= 55) {
            throw new WebAuthnException('Attested data should be present but is missing', WebAuthnException::INVALID_DATA);
        }

        // The AAGUID of the authenticator
        $attestedCData->aaguid = \substr($binary, 37, 16);

        //Byte length L of Credential ID, 16-bit unsigned big-endian integer.
        $length = \unpack('nlength', \substr($binary, 53, 2))['length'];
        $attestedCData->credentialId = \substr($binary, 55, $length);

        // set end offset
        $endOffset = 55 + $length;

        // extract public key
        $attestedCData->credentialPublicKey = $this->_readCredentialPublicKey($binary, 55 + $length, $endOffset);

        return $attestedCData;
    }

    /**
     * reads COSE key-encoded elliptic curve public key in EC2 format
     * @param string $binary
     * @param int $endOffset
     * @return \stdClass
     * @throws WebAuthnException
     */
    private function _readCredentialPublicKey($binary, $offset, &$endOffset) {
        $enc = CborDecoder::decodeInPlace($binary, $offset, $endOffset);

        // COSE key-encoded elliptic curve public key in EC2 format
        $credPKey = new \stdClass();
        $credPKey->kty = $enc[self::$_COSE_KTY];
        $credPKey->alg = $enc[self::$_COSE_ALG];

        switch ($credPKey->alg) {
            case self::$_EC2_ES256: $this->_readCredentialPublicKeyES256($credPKey, $enc); break;
            case self::$_RSA_RS256: $this->_readCredentialPublicKeyRS256($credPKey, $enc); break;
            case self::$_OKP_EDDSA: $this->_readCredentialPublicKeyEDDSA($credPKey, $enc); break;
        }

        return $credPKey;
    }

    /**
     * extract EDDSA informations from cose
     * @param \stdClass $credPKey
     * @param \stdClass $enc
     * @throws WebAuthnException
     */
    private function _readCredentialPublicKeyEDDSA(&$credPKey, $enc) {
        $credPKey->crv = $enc[self::$_COSE_CRV];
        $credPKey->x   = $enc[self::$_COSE_X] instanceof ByteBuffer ? $enc[self::$_COSE_X]->getBinaryString() : null;
        unset ($enc);

        // Validation
        if ($credPKey->kty !== self::$_OKP_TYPE) {
            throw new WebAuthnException('public key not in OKP format', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if ($credPKey->alg !== self::$_OKP_EDDSA) {
            throw new WebAuthnException('signature algorithm not EdDSA', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if ($credPKey->crv !== self::$_OKP_ED25519) {
            throw new WebAuthnException('curve not Ed25519', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if (\strlen($credPKey->x) !== 32) {
            throw new WebAuthnException('Invalid X-coordinate', WebAuthnException::INVALID_PUBLIC_KEY);
        }
    }

    /**
     * extract ES256 informations from cose
     * @param \stdClass $credPKey
     * @param \stdClass $enc
     * @throws WebAuthnException
     */
    private function _readCredentialPublicKeyES256(&$credPKey, $enc) {
        $credPKey->crv = $enc[self::$_COSE_CRV];
        $credPKey->x   = $enc[self::$_COSE_X] instanceof ByteBuffer ? $enc[self::$_COSE_X]->getBinaryString() : null;
        $credPKey->y   = $enc[self::$_COSE_Y] instanceof ByteBuffer ? $enc[self::$_COSE_Y]->getBinaryString() : null;
        unset ($enc);

        // Validation
        if ($credPKey->kty !== self::$_EC2_TYPE) {
            throw new WebAuthnException('public key not in EC2 format', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if ($credPKey->alg !== self::$_EC2_ES256) {
            throw new WebAuthnException('signature algorithm not ES256', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if ($credPKey->crv !== self::$_EC2_P256) {
            throw new WebAuthnException('curve not P-256', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if (\strlen($credPKey->x) !== 32) {
            throw new WebAuthnException('Invalid X-coordinate', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if (\strlen($credPKey->y) !== 32) {
            throw new WebAuthnException('Invalid Y-coordinate', WebAuthnException::INVALID_PUBLIC_KEY);
        }
    }

    /**
     * extract RS256 informations from COSE
     * @param \stdClass $credPKey
     * @param \stdClass $enc
     * @throws WebAuthnException
     */
    private function _readCredentialPublicKeyRS256(&$credPKey, $enc) {
        $credPKey->n = $enc[self::$_COSE_N] instanceof ByteBuffer ? $enc[self::$_COSE_N]->getBinaryString() : null;
        $credPKey->e = $enc[self::$_COSE_E] instanceof ByteBuffer ? $enc[self::$_COSE_E]->getBinaryString() : null;
        unset ($enc);

        // Validation
        if ($credPKey->kty !== self::$_RSA_TYPE) {
            throw new WebAuthnException('public key not in RSA format', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if ($credPKey->alg !== self::$_RSA_RS256) {
            throw new WebAuthnException('signature algorithm not ES256', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if (\strlen($credPKey->n) !== 256) {
            throw new WebAuthnException('Invalid RSA modulus', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        if (\strlen($credPKey->e) !== 3) {
            throw new WebAuthnException('Invalid RSA public exponent', WebAuthnException::INVALID_PUBLIC_KEY);
        }

    }

    /**
     * reads cbor encoded extension data.
     * @param string $binary
     * @return array
     * @throws WebAuthnException
     */
    private function _readExtensionData($binary) {
        $ext = CborDecoder::decode($binary);
        if (!\is_array($ext)) {
            throw new WebAuthnException('invalid extension data', WebAuthnException::INVALID_DATA);
        }

        return $ext;
    }


    // ---------------
    // DER functions
    // ---------------

    private function _der_length($len) {
        if ($len < 128) {
            return \chr($len);
        }
        $lenBytes = '';
        while ($len > 0) {
            $lenBytes = \chr($len % 256) . $lenBytes;
            $len = \intdiv($len, 256);
        }
        return \chr(0x80 | \strlen($lenBytes)) . $lenBytes;
    }

    private function _der_sequence($contents) {
        return "\x30" . $this->_der_length(\strlen($contents)) . $contents;
    }

    private function _der_oid($encoded) {
        return "\x06" . $this->_der_length(\strlen($encoded)) . $encoded;
    }

    private function _der_bitString($bytes) {
        return "\x03" . $this->_der_length(\strlen($bytes) + 1) . "\x00" . $bytes;
    }

    private function _der_nullValue() {
        return "\x05\x00";
    }

    private function _der_unsignedInteger($bytes) {
        $len = \strlen($bytes);

        // Remove leading zero bytes
        for ($i = 0; $i < ($len - 1); $i++) {
            if (\ord($bytes[$i]) !== 0) {
                break;
            }
        }
        if ($i !== 0) {
            $bytes = \substr($bytes, $i);
        }

        // If most significant bit is set, prefix with another zero to prevent it being seen as negative number
        if ((\ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->_der_length(\strlen($bytes)) . $bytes;
    }
}

/* Upstream: Attestation/Format/FormatBase.php */



namespace P1\WebAuthn\Attestation\Format;
use P1\WebAuthn\WebAuthnException;
use P1\WebAuthn\Attestation\AuthenticatorData;


abstract class FormatBase {
    protected $_attestationObject = null;
    protected $_authenticatorData = null;
    protected $_x5c_chain = array();
    protected $_x5c_tempFile = null;

    /**
     *
     * @param Array $AttestionObject
     * @param AuthenticatorData $authenticatorData
     */
    public function __construct($AttestionObject, AuthenticatorData $authenticatorData) {
        $this->_attestationObject = $AttestionObject;
        $this->_authenticatorData = $authenticatorData;
    }

    /**
     *
     */
    public function __destruct() {
        // delete X.509 chain certificate file after use
        if ($this->_x5c_tempFile && \is_file($this->_x5c_tempFile)) {
            \unlink($this->_x5c_tempFile);
        }
    }

    /**
     * returns the certificate chain in PEM format
     * @return string|null
     */
    public function getCertificateChain() {
        if ($this->_x5c_tempFile && \is_file($this->_x5c_tempFile)) {
            return \file_get_contents($this->_x5c_tempFile);
        }
        return null;
    }

    /**
     * returns the key X.509 certificate in PEM format
     * @return string
     */
    public function getCertificatePem() {
        // need to be overwritten
        return null;
    }

    /**
     * checks validity of the signature
     * @param string $clientDataHash
     * @return bool
     * @throws WebAuthnException
     */
    public function validateAttestation($clientDataHash) {
        // need to be overwritten
        return false;
    }

    /**
     * validates the certificate against root certificates
     * @param array $rootCas
     * @return boolean
     * @throws WebAuthnException
     */
    public function validateRootCertificate($rootCas) {
        // need to be overwritten
        return false;
    }


    /**
     * create a PEM encoded certificate with X.509 binary data
     * @param string $x5c
     * @return string
     */
    protected function _createCertificatePem($x5c) {
        $pem = '-----BEGIN CERTIFICATE-----' . "\n";
        $pem .= \chunk_split(\base64_encode($x5c), 64, "\n");
        $pem .= '-----END CERTIFICATE-----' . "\n";
        return $pem;
    }

    /**
     * creates a PEM encoded chain file
     * @return string|null
     */
    protected function _createX5cChainFile() {
        $content = '';
        if (\is_array($this->_x5c_chain) && \count($this->_x5c_chain) > 0) {
            foreach ($this->_x5c_chain as $x5c) {
                $certInfo = \openssl_x509_parse($this->_createCertificatePem($x5c));

                // check if certificate is self signed
                if (\is_array($certInfo) && \is_array($certInfo['issuer']) && \is_array($certInfo['subject'])) {
                    $selfSigned = false;

                    $subjectKeyIdentifier = $certInfo['extensions']['subjectKeyIdentifier'] ?? null;
                    $authorityKeyIdentifier = $certInfo['extensions']['authorityKeyIdentifier'] ?? null;

                    if ($authorityKeyIdentifier && substr($authorityKeyIdentifier, 0, 6) === 'keyid:') {
                        $authorityKeyIdentifier = substr($authorityKeyIdentifier, 6);
                    }
                    if ($subjectKeyIdentifier && substr($subjectKeyIdentifier, 0, 6) === 'keyid:') {
                        $subjectKeyIdentifier = substr($subjectKeyIdentifier, 6);
                    }

                    if (($subjectKeyIdentifier && !$authorityKeyIdentifier) || ($authorityKeyIdentifier && $authorityKeyIdentifier === $subjectKeyIdentifier)) {
                        $selfSigned = true;
                    }

                    if (!$selfSigned) {
                        $content .= "\n" . $this->_createCertificatePem($x5c) . "\n";
                    }
                }
            }
        }

        if ($content) {
            $this->_x5c_tempFile = \tempnam(\sys_get_temp_dir(), 'x5c_');
            if (\file_put_contents($this->_x5c_tempFile, $content) !== false) {
                return $this->_x5c_tempFile;
            }
        }

        return null;
    }


    /**
     * returns the name and openssl key for provided cose number.
     * @param int $coseNumber
     * @return \stdClass|null
     */
    protected function _getCoseAlgorithm($coseNumber) {
        // https://www.iana.org/assignments/cose/cose.xhtml#algorithms
        $coseAlgorithms = array(
            array(
                'hash' => 'SHA1',
                'openssl' => OPENSSL_ALGO_SHA1,
                'cose' => array(
                    -65535  // RS1
                )),

            array(
                'hash' => 'SHA256',
                'openssl' => OPENSSL_ALGO_SHA256,
                'cose' => array(
                    -257, // RS256
                    -37,  // PS256
                    -7,   // ES256
                    5     // HMAC256
                )),

            array(
                'hash' => 'SHA384',
                'openssl' => OPENSSL_ALGO_SHA384,
                'cose' => array(
                    -258, // RS384
                    -38,  // PS384
                    -35,  // ES384
                    6     // HMAC384
                )),

            array(
                'hash' => 'SHA512',
                'openssl' => OPENSSL_ALGO_SHA512,
                'cose' => array(
                    -259, // RS512
                    -39,  // PS512
                    -36,  // ES512
                    7     // HMAC512
                ))
        );

        foreach ($coseAlgorithms as $coseAlgorithm) {
            if (\in_array($coseNumber, $coseAlgorithm['cose'], true)) {
                $return = new \stdClass();
                $return->hash = $coseAlgorithm['hash'];
                $return->openssl = $coseAlgorithm['openssl'];
                return $return;
            }
        }

        return null;
    }
}

/* Upstream: Attestation/Format/None.php */



namespace P1\WebAuthn\Attestation\Format;
use P1\WebAuthn\Attestation\AuthenticatorData;
use P1\WebAuthn\WebAuthnException;

class None extends FormatBase {


    public function __construct($AttestionObject, AuthenticatorData $authenticatorData) {
        parent::__construct($AttestionObject, $authenticatorData);
    }


    /*
     * returns the key certificate in PEM format
     * @return string
     */
    public function getCertificatePem() {
        return null;
    }

    /**
     * @param string $clientDataHash
     */
    public function validateAttestation($clientDataHash) {
        return true;
    }

    /**
     * validates the certificate against root certificates.
     * Format 'none' does not contain any ca, so always false.
     * @param array $rootCas
     * @return boolean
     * @throws WebAuthnException
     */
    public function validateRootCertificate($rootCas) {
        return false;
    }
}

/* Upstream: Attestation/AttestationObject.php */


namespace P1\WebAuthn\Attestation;
use P1\WebAuthn\WebAuthnException;
use P1\WebAuthn\CBOR\CborDecoder;
use P1\WebAuthn\Binary\ByteBuffer;

/**
 * @author Lukas Buchs
 * @license https://github.com/lbuchs/WebAuthn/blob/master/LICENSE MIT
 */
class AttestationObject {
    private $_authenticatorData;
    private $_attestationFormat;
    private $_attestationFormatName;

    public function __construct($binary , $allowedFormats) {
        $enc = CborDecoder::decode($binary);
        // validation
        if (!\is_array($enc) || !\array_key_exists('fmt', $enc) || !is_string($enc['fmt'])) {
            throw new WebAuthnException('invalid attestation format', WebAuthnException::INVALID_DATA);
        }

        if (!\array_key_exists('attStmt', $enc) || !\is_array($enc['attStmt'])) {
            throw new WebAuthnException('invalid attestation format (attStmt not available)', WebAuthnException::INVALID_DATA);
        }

        if (!\array_key_exists('authData', $enc) || !\is_object($enc['authData']) || !($enc['authData'] instanceof ByteBuffer)) {
            throw new WebAuthnException('invalid attestation format (authData not available)', WebAuthnException::INVALID_DATA);
        }

        $this->_authenticatorData = new AuthenticatorData($enc['authData']->getBinaryString());
        $this->_attestationFormatName = $enc['fmt'];

        // Format ok?
        if (!in_array($this->_attestationFormatName, $allowedFormats)) {
            throw new WebAuthnException('invalid atttestation format: ' . $this->_attestationFormatName, WebAuthnException::INVALID_DATA);
        }


        switch ($this->_attestationFormatName) {
            case 'android-key': $this->_attestationFormat = new Format\AndroidKey($enc, $this->_authenticatorData); break;
            case 'android-safetynet': $this->_attestationFormat = new Format\AndroidSafetyNet($enc, $this->_authenticatorData); break;
            case 'apple': $this->_attestationFormat = new Format\Apple($enc, $this->_authenticatorData); break;
            case 'fido-u2f': $this->_attestationFormat = new Format\U2f($enc, $this->_authenticatorData); break;
            case 'none': $this->_attestationFormat = new Format\None($enc, $this->_authenticatorData); break;
            case 'packed': $this->_attestationFormat = new Format\Packed($enc, $this->_authenticatorData); break;
            case 'tpm': $this->_attestationFormat = new Format\Tpm($enc, $this->_authenticatorData); break;
            default: throw new WebAuthnException('invalid attestation format: ' . $enc['fmt'], WebAuthnException::INVALID_DATA);
        }
    }

    /**
     * returns the attestation format name
     * @return string
     */
    public function getAttestationFormatName() {
        return $this->_attestationFormatName;
    }

    /**
     * returns the attestation format class
     * @return Format\FormatBase
     */
    public function getAttestationFormat() {
        return $this->_attestationFormat;
    }

    /**
     * returns the attestation public key in PEM format
     * @return AuthenticatorData
     */
    public function getAuthenticatorData() {
        return $this->_authenticatorData;
    }

    /**
     * returns the certificate chain as PEM
     * @return string|null
     */
    public function getCertificateChain() {
        return $this->_attestationFormat->getCertificateChain();
    }

    /**
     * return the certificate issuer as string
     * @return string
     */
    public function getCertificateIssuer() {
        $pem = $this->getCertificatePem();
        $issuer = '';
        if ($pem) {
            $certInfo = \openssl_x509_parse($pem);
            if (\is_array($certInfo) && \array_key_exists('issuer', $certInfo) && \is_array($certInfo['issuer'])) {

                $cn = $certInfo['issuer']['CN'] ?? '';
                $o = $certInfo['issuer']['O'] ?? '';
                $ou = $certInfo['issuer']['OU'] ?? '';

                if ($cn) {
                    $issuer .= $cn;
                }
                if ($issuer && ($o || $ou)) {
                    $issuer .= ' (' . trim($o . ' ' . $ou) . ')';
                } else {
                    $issuer .= trim($o . ' ' . $ou);
                }
            }
        }

        return $issuer;
    }

    /**
     * return the certificate subject as string
     * @return string
     */
    public function getCertificateSubject() {
        $pem = $this->getCertificatePem();
        $subject = '';
        if ($pem) {
            $certInfo = \openssl_x509_parse($pem);
            if (\is_array($certInfo) && \array_key_exists('subject', $certInfo) && \is_array($certInfo['subject'])) {

                $cn = $certInfo['subject']['CN'] ?? '';
                $o = $certInfo['subject']['O'] ?? '';
                $ou = $certInfo['subject']['OU'] ?? '';

                if ($cn) {
                    $subject .= $cn;
                }
                if ($subject && ($o || $ou)) {
                    $subject .= ' (' . trim($o . ' ' . $ou) . ')';
                } else {
                    $subject .= trim($o . ' ' . $ou);
                }
            }
        }

        return $subject;
    }

    /**
     * returns the key certificate in PEM format
     * @return string
     */
    public function getCertificatePem() {
        return $this->_attestationFormat->getCertificatePem();
    }

    /**
     * checks validity of the signature
     * @param string $clientDataHash
     * @return bool
     * @throws WebAuthnException
     */
    public function validateAttestation($clientDataHash) {
        return $this->_attestationFormat->validateAttestation($clientDataHash);
    }

    /**
     * validates the certificate against root certificates
     * @param array $rootCas
     * @return boolean
     * @throws WebAuthnException
     */
    public function validateRootCertificate($rootCas) {
        return $this->_attestationFormat->validateRootCertificate($rootCas);
    }

    /**
     * checks if the RpId-Hash is valid
     * @param string$rpIdHash
     * @return bool
     */
    public function validateRpIdHash($rpIdHash) {
        return $rpIdHash === $this->_authenticatorData->getRpIdHash();
    }
}

/* Upstream: WebAuthn.php */


namespace P1\WebAuthn;
use P1\WebAuthn\Binary\ByteBuffer;

/**
 * WebAuthn
 * @author Lukas Buchs
 * @license https://github.com/lbuchs/WebAuthn/blob/master/LICENSE MIT
 */
class WebAuthn {
    // relying party
    private $_rpName;
    private $_rpId;
    private $_rpIdHash;
    private $_challenge;
    private $_signatureCounter;
    private $_caFiles;
    private $_formats;
    private $_androidKeyHashes;

    /**
     * Initialize a new WebAuthn server
     * @param string $rpName the relying party name
     * @param string $rpId the relying party ID = the domain name
     * @param bool $useBase64UrlEncoding true to use base64 url encoding for binary data in json objects. Default is a RFC 1342-Like serialized string.
     * @throws WebAuthnException
     */
    public function __construct($rpName, $rpId, $allowedFormats=null, $useBase64UrlEncoding=false) {
        $this->_rpName = $rpName;
        $this->_rpId = $rpId;
        $this->_rpIdHash = \hash('sha256', $rpId, true);
        ByteBuffer::$useBase64UrlEncoding = !!$useBase64UrlEncoding;
        $supportedFormats = array('android-key', 'android-safetynet', 'apple', 'fido-u2f', 'none', 'packed', 'tpm');

        if (!\function_exists('\openssl_open')) {
            throw new WebAuthnException('OpenSSL-Module not installed');
        }

        if (!\in_array('SHA256', \array_map('\strtoupper', \openssl_get_md_methods()))) {
            throw new WebAuthnException('SHA256 not supported by this openssl installation.');
        }

        // default: all format
        if (!is_array($allowedFormats)) {
            $allowedFormats = $supportedFormats;
        }
        $this->_formats = $allowedFormats;

        // validate formats
        $invalidFormats = \array_diff($this->_formats, $supportedFormats);
        if (!$this->_formats || $invalidFormats) {
            throw new WebAuthnException('invalid formats on construct: ' . implode(', ', $invalidFormats));
        }
    }

    /**
     * add a root certificate to verify new registrations
     * @param string $path file path of / directory with root certificates
     * @param array|null $certFileExtensions if adding a direction, all files with provided extension are added. default: pem, crt, cer, der
     */
    public function addRootCertificates($path, $certFileExtensions=null) {
        if (!\is_array($this->_caFiles)) {
            $this->_caFiles = [];
        }
        if ($certFileExtensions === null) {
            $certFileExtensions = array('pem', 'crt', 'cer', 'der');
        }
        $path = \rtrim(\trim($path), '\\/');
        if (\is_dir($path)) {
            foreach (\scandir($path) as $ca) {
                if (\is_file($path . DIRECTORY_SEPARATOR . $ca) && \in_array(\strtolower(\pathinfo($ca, PATHINFO_EXTENSION)), $certFileExtensions)) {
                    $this->addRootCertificates($path . DIRECTORY_SEPARATOR . $ca);
                }
            }
        } else if (\is_file($path) && !\in_array(\realpath($path), $this->_caFiles)) {
            $this->_caFiles[] = \realpath($path);
        }
    }

    /**
     * add key hashes for android verification
     * @param array<string> $hashes
     * @return void
     */
    public function addAndroidKeyHashes($hashes) {
        if (!\is_array($this->_androidKeyHashes)) {
            $this->_androidKeyHashes = [];
        }

        foreach ($hashes as $hash) {
            if (is_string($hash)) {
                $this->_androidKeyHashes[] = $hash;
            }
        }
    }

    /**
     * Returns the generated challenge to save for later validation
     * @return ByteBuffer
     */
    public function getChallenge() {
        return $this->_challenge;
    }

    /**
     * generates the object for a key registration
     * provide this data to navigator.credentials.create
     * @param string $userId
     * @param string $userName
     * @param string $userDisplayName
     * @param int $timeout timeout in seconds
     * @param bool|string $requireResidentKey      'required', if the key should be stored by the authentication device
     *                                             Valid values:
     *                                             true = required
     *                                             false = preferred
     *                                             string 'required' 'preferred' 'discouraged'
     * @param bool|string $requireUserVerification indicates that you require user verification and will fail the operation
     *                                             if the response does not have the UV flag set.
     *                                             Valid values:
     *                                             true = required
     *                                             false = preferred
     *                                             string 'required' 'preferred' 'discouraged'
     * @param bool|null $crossPlatformAttachment   true for cross-platform devices (eg. fido usb),
     *                                             false for platform devices (eg. windows hello, android safetynet),
     *                                             null for both
     * @param array $excludeCredentialIds a array of ids, which are already registered, to prevent re-registration
     * @return \stdClass
     */
    public function getCreateArgs($userId, $userName, $userDisplayName, $timeout=20, $requireResidentKey=false, $requireUserVerification=false, $crossPlatformAttachment=null, $excludeCredentialIds=[]) {

        $args = new \stdClass();
        $args->publicKey = new \stdClass();

        // relying party
        $args->publicKey->rp = new \stdClass();
        $args->publicKey->rp->name = $this->_rpName;
        $args->publicKey->rp->id = $this->_rpId;

        $args->publicKey->authenticatorSelection = new \stdClass();
        $args->publicKey->authenticatorSelection->userVerification = 'preferred';

        // validate User Verification Requirement
        if (\is_bool($requireUserVerification)) {
            $args->publicKey->authenticatorSelection->userVerification = $requireUserVerification ? 'required' : 'preferred';

        } else if (\is_string($requireUserVerification) && \in_array(\strtolower($requireUserVerification), ['required', 'preferred', 'discouraged'])) {
            $args->publicKey->authenticatorSelection->userVerification = \strtolower($requireUserVerification);
        }

        // validate Resident Key Requirement
        if (\is_bool($requireResidentKey) && $requireResidentKey) {
            $args->publicKey->authenticatorSelection->requireResidentKey = true;
            $args->publicKey->authenticatorSelection->residentKey = 'required';

        } else if (\is_string($requireResidentKey) && \in_array(\strtolower($requireResidentKey), ['required', 'preferred', 'discouraged'])) {
            $requireResidentKey = \strtolower($requireResidentKey);
            $args->publicKey->authenticatorSelection->residentKey = $requireResidentKey;
            $args->publicKey->authenticatorSelection->requireResidentKey = $requireResidentKey === 'required';
        }

        // filte authenticators attached with the specified authenticator attachment modality
        if (\is_bool($crossPlatformAttachment)) {
            $args->publicKey->authenticatorSelection->authenticatorAttachment = $crossPlatformAttachment ? 'cross-platform' : 'platform';
        }

        // user
        $args->publicKey->user = new \stdClass();
        $args->publicKey->user->id = new ByteBuffer($userId); // binary
        $args->publicKey->user->name = $userName;
        $args->publicKey->user->displayName = $userDisplayName;

        // supported algorithms
        $args->publicKey->pubKeyCredParams = [];

        if (function_exists('sodium_crypto_sign_verify_detached') || \in_array('ed25519', \openssl_get_curve_names(), true)) {
            $tmp = new \stdClass();
            $tmp->type = 'public-key';
            $tmp->alg = -8; // EdDSA
            $args->publicKey->pubKeyCredParams[] = $tmp;
            unset ($tmp);
        }

        if (\in_array('prime256v1', \openssl_get_curve_names(), true)) {
            $tmp = new \stdClass();
            $tmp->type = 'public-key';
            $tmp->alg = -7; // ES256
            $args->publicKey->pubKeyCredParams[] = $tmp;
            unset ($tmp);
        }

        $tmp = new \stdClass();
        $tmp->type = 'public-key';
        $tmp->alg = -257; // RS256
        $args->publicKey->pubKeyCredParams[] = $tmp;
        unset ($tmp);

        // if there are root certificates added, we need direct attestation to validate
        // against the root certificate. If there are no root-certificates added,
        // anonymization ca are also accepted, because we can't validate the root anyway.
        $attestation = 'indirect';
        if (\is_array($this->_caFiles)) {
            $attestation = 'direct';
        }

        $args->publicKey->attestation = \count($this->_formats) === 1 && \in_array('none', $this->_formats) ? 'none' : $attestation;
        $args->publicKey->extensions = new \stdClass();
        $args->publicKey->extensions->exts = true;
        $args->publicKey->timeout = $timeout * 1000; // microseconds
        $args->publicKey->challenge = $this->_createChallenge(); // binary

        //prevent re-registration by specifying existing credentials
        $args->publicKey->excludeCredentials = [];

        if (is_array($excludeCredentialIds)) {
            foreach ($excludeCredentialIds as $id) {
                $tmp = new \stdClass();
                $tmp->id = $id instanceof ByteBuffer ? $id : new ByteBuffer($id);  // binary
                $tmp->type = 'public-key';
                $tmp->transports = array('usb', 'nfc', 'ble', 'hybrid', 'internal');
                $args->publicKey->excludeCredentials[] = $tmp;
                unset ($tmp);
            }
        }

        return $args;
    }

    /**
     * generates the object for key validation
     * Provide this data to navigator.credentials.get
     * @param array $credentialIds binary
     * @param int $timeout timeout in seconds
     * @param bool $allowUsb allow removable USB
     * @param bool $allowNfc allow Near Field Communication (NFC)
     * @param bool $allowBle allow Bluetooth
     * @param bool $allowHybrid allow a combination of (often separate) data-transport and proximity mechanisms.
     * @param bool $allowInternal allow client device-specific transport. These authenticators are not removable from the client device.
     * @param bool|string $requireUserVerification indicates that you require user verification and will fail the operation
     *                                             if the response does not have the UV flag set.
     *                                             Valid values:
     *                                             true = required
     *                                             false = preferred
     *                                             string 'required' 'preferred' 'discouraged'
     * @return \stdClass
     */
    public function getGetArgs($credentialIds=[], $timeout=20, $allowUsb=true, $allowNfc=true, $allowBle=true, $allowHybrid=true, $allowInternal=true, $requireUserVerification=false) {

        // validate User Verification Requirement
        if (\is_bool($requireUserVerification)) {
            $requireUserVerification = $requireUserVerification ? 'required' : 'preferred';
        } else if (\is_string($requireUserVerification) && \in_array(\strtolower($requireUserVerification), ['required', 'preferred', 'discouraged'])) {
            $requireUserVerification = \strtolower($requireUserVerification);
        } else {
            $requireUserVerification = 'preferred';
        }

        $args = new \stdClass();
        $args->publicKey = new \stdClass();
        $args->publicKey->timeout = $timeout * 1000; // microseconds
        $args->publicKey->challenge = $this->_createChallenge();  // binary
        $args->publicKey->userVerification = $requireUserVerification;
        $args->publicKey->rpId = $this->_rpId;

        if (\is_array($credentialIds) && \count($credentialIds) > 0) {
            $args->publicKey->allowCredentials = [];

            foreach ($credentialIds as $id) {
                $tmp = new \stdClass();
                $tmp->id = $id instanceof ByteBuffer ? $id : new ByteBuffer($id);  // binary
                $tmp->transports = [];

                if ($allowUsb) {
                    $tmp->transports[] = 'usb';
                }
                if ($allowNfc) {
                    $tmp->transports[] = 'nfc';
                }
                if ($allowBle) {
                    $tmp->transports[] = 'ble';
                }
                if ($allowHybrid) {
                    $tmp->transports[] = 'hybrid';
                }
                if ($allowInternal) {
                    $tmp->transports[] = 'internal';
                }

                $tmp->type = 'public-key';
                $args->publicKey->allowCredentials[] = $tmp;
                unset ($tmp);
            }
        }

        return $args;
    }

    /**
     * returns the new signature counter value.
     * returns null if there is no counter
     * @return ?int
     */
    public function getSignatureCounter() {
        return \is_int($this->_signatureCounter) ? $this->_signatureCounter : null;
    }

    /**
     * process a create request and returns data to save for future logins
     * @param string $clientDataJSON binary from browser
     * @param string $attestationObject binary from browser
     * @param string|ByteBuffer $challenge binary used challange
     * @param bool $requireUserVerification true, if the device must verify user (e.g. by biometric data or pin)
     * @param bool $requireUserPresent false, if the device must NOT check user presence (e.g. by pressing a button)
     * @param bool $failIfRootMismatch false, if there should be no error thrown if root certificate doesn't match
     * @param bool $requireCtsProfileMatch false, if you don't want to check if the device is approved as a Google-certified Android device.
     * @return \stdClass
     * @throws WebAuthnException
     */
    public function processCreate($clientDataJSON, $attestationObject, $challenge, $requireUserVerification=false, $requireUserPresent=true, $failIfRootMismatch=true, $requireCtsProfileMatch=true) {
        $clientDataHash = \hash('sha256', $clientDataJSON, true);
        $clientData = \json_decode($clientDataJSON);
        $challenge = $challenge instanceof ByteBuffer ? $challenge : new ByteBuffer($challenge);

        // security: https://www.w3.org/TR/webauthn/#registering-a-new-credential

        // 2. Let C, the client data claimed as collected during the credential creation,
        //    be the result of running an implementation-specific JSON parser on JSONtext.
        if (!\is_object($clientData)) {
            throw new WebAuthnException('invalid client data', WebAuthnException::INVALID_DATA);
        }

        // 3. Verify that the value of C.type is webauthn.create.
        if (!\property_exists($clientData, 'type') || $clientData->type !== 'webauthn.create') {
            throw new WebAuthnException('invalid type', WebAuthnException::INVALID_TYPE);
        }

        // 4. Verify that the value of C.challenge matches the challenge that was sent to the authenticator in the create() call.
        if (!\property_exists($clientData, 'challenge') || ByteBuffer::fromBase64Url($clientData->challenge)->getBinaryString() !== $challenge->getBinaryString()) {
            throw new WebAuthnException('invalid challenge', WebAuthnException::INVALID_CHALLENGE);
        }

        // 5. Verify that the value of C.origin matches the Relying Party's origin.
        if (!\property_exists($clientData, 'origin') || !$this->_checkOrigin($clientData->origin)) {
            throw new WebAuthnException('invalid origin', WebAuthnException::INVALID_ORIGIN);
        }

        // Attestation
        $attestationObject = new Attestation\AttestationObject($attestationObject, $this->_formats);

        // 9. Verify that the RP ID hash in authData is indeed the SHA-256 hash of the RP ID expected by the RP.
        if (!$attestationObject->validateRpIdHash($this->_rpIdHash)) {
            throw new WebAuthnException('invalid rpId hash', WebAuthnException::INVALID_RELYING_PARTY);
        }

        // 14. Verify that attStmt is a correct attestation statement, conveying a valid attestation signature
        if (!$attestationObject->validateAttestation($clientDataHash)) {
            throw new WebAuthnException('invalid certificate signature', WebAuthnException::INVALID_SIGNATURE);
        }

        // Android-SafetyNet: if required, check for Compatibility Testing Suite (CTS).
        if ($requireCtsProfileMatch && $attestationObject->getAttestationFormat() instanceof Attestation\Format\AndroidSafetyNet) {
            if (!$attestationObject->getAttestationFormat()->ctsProfileMatch()) {
                 throw new WebAuthnException('invalid ctsProfileMatch: device is not approved as a Google-certified Android device.', WebAuthnException::ANDROID_NOT_TRUSTED);
            }
        }

        // 15. If validation is successful, obtain a list of acceptable trust anchors
        $rootValid = is_array($this->_caFiles) ? $attestationObject->validateRootCertificate($this->_caFiles) : null;
        if ($failIfRootMismatch && is_array($this->_caFiles) && !$rootValid) {
            throw new WebAuthnException('invalid root certificate', WebAuthnException::CERTIFICATE_NOT_TRUSTED);
        }

        // 10. Verify that the User Present bit of the flags in authData is set.
        $userPresent = $attestationObject->getAuthenticatorData()->getUserPresent();
        if ($requireUserPresent && !$userPresent) {
            throw new WebAuthnException('user not present during authentication', WebAuthnException::USER_PRESENT);
        }

        // 11. If user verification is required for this registration, verify that the User Verified bit of the flags in authData is set.
        $userVerified = $attestationObject->getAuthenticatorData()->getUserVerified();
        if ($requireUserVerification && !$userVerified) {
            throw new WebAuthnException('user not verified during authentication', WebAuthnException::USER_VERIFICATED);
        }

        $signCount = $attestationObject->getAuthenticatorData()->getSignCount();
        if ($signCount > 0) {
            $this->_signatureCounter = $signCount;
        }

        // prepare data to store for future logins
        $data = new \stdClass();
        $data->rpId = $this->_rpId;
        $data->attestationFormat = $attestationObject->getAttestationFormatName();
        $data->credentialId = $attestationObject->getAuthenticatorData()->getCredentialId();
        $data->credentialPublicKey = $attestationObject->getAuthenticatorData()->getPublicKeyPem();
        $data->certificateChain = $attestationObject->getCertificateChain();
        $data->certificate = $attestationObject->getCertificatePem();
        $data->certificateIssuer = $attestationObject->getCertificateIssuer();
        $data->certificateSubject = $attestationObject->getCertificateSubject();
        $data->signatureCounter = $this->_signatureCounter;
        $data->AAGUID = $attestationObject->getAuthenticatorData()->getAAGUID();
        $data->rootValid = $rootValid;
        $data->userPresent = $userPresent;
        $data->userVerified = $userVerified;
    	$data->isBackupEligible = $attestationObject->getAuthenticatorData()->getIsBackupEligible();
        $data->isBackedUp = $attestationObject->getAuthenticatorData()->getIsBackup();
        return $data;
    }


    /**
     * process a get request
     * @param string $clientDataJSON binary from browser
     * @param string $authenticatorData binary from browser
     * @param string $signature binary from browser
     * @param string $credentialPublicKey string PEM-formated public key from used credentialId
     * @param string|ByteBuffer $challenge  binary from used challange
     * @param int $prevSignatureCnt signature count value of the last login
     * @param bool $requireUserVerification true, if the device must verify user (e.g. by biometric data or pin)
     * @param bool $requireUserPresent true, if the device must check user presence (e.g. by pressing a button)
     * @return boolean true if get is successful
     * @throws WebAuthnException
     */
    public function processGet($clientDataJSON, $authenticatorData, $signature, $credentialPublicKey, $challenge, $prevSignatureCnt=null, $requireUserVerification=false, $requireUserPresent=true) {
        $authenticatorObj = new Attestation\AuthenticatorData($authenticatorData);
        $clientDataHash = \hash('sha256', $clientDataJSON, true);
        $clientData = \json_decode($clientDataJSON);
        $challenge = $challenge instanceof ByteBuffer ? $challenge : new ByteBuffer($challenge);

        // https://www.w3.org/TR/webauthn/#verifying-assertion

        // 1. If the allowCredentials option was given when this authentication ceremony was initiated,
        //    verify that credential.id identifies one of the public key credentials that were listed in allowCredentials.
        //    -> TO BE VERIFIED BY IMPLEMENTATION

        // 2. If credential.response.userHandle is present, verify that the user identified
        //    by this value is the owner of the public key credential identified by credential.id.
        //    -> TO BE VERIFIED BY IMPLEMENTATION

        // 3. Using credential’s id attribute (or the corresponding rawId, if base64url encoding is
        //    inappropriate for your use case), look up the corresponding credential public key.
        //    -> TO BE LOOKED UP BY IMPLEMENTATION

        // 5. Let JSONtext be the result of running UTF-8 decode on the value of cData.
        if (!\is_object($clientData)) {
            throw new WebAuthnException('invalid client data', WebAuthnException::INVALID_DATA);
        }

        // 7. Verify that the value of C.type is the string webauthn.get.
        if (!\property_exists($clientData, 'type') || $clientData->type !== 'webauthn.get') {
            throw new WebAuthnException('invalid type', WebAuthnException::INVALID_TYPE);
        }

        // 8. Verify that the value of C.challenge matches the challenge that was sent to the
        //    authenticator in the PublicKeyCredentialRequestOptions passed to the get() call.
        if (!\property_exists($clientData, 'challenge') || ByteBuffer::fromBase64Url($clientData->challenge)->getBinaryString() !== $challenge->getBinaryString()) {
            throw new WebAuthnException('invalid challenge', WebAuthnException::INVALID_CHALLENGE);
        }

        // 9. Verify that the value of C.origin matches the Relying Party's origin.
        if (!\property_exists($clientData, 'origin') || !$this->_checkOrigin($clientData->origin)) {
            throw new WebAuthnException('invalid origin', WebAuthnException::INVALID_ORIGIN);
        }

        // 11. Verify that the rpIdHash in authData is the SHA-256 hash of the RP ID expected by the Relying Party.
        if ($authenticatorObj->getRpIdHash() !== $this->_rpIdHash) {
            throw new WebAuthnException('invalid rpId hash', WebAuthnException::INVALID_RELYING_PARTY);
        }

        // 12. Verify that the User Present bit of the flags in authData is set
        if ($requireUserPresent && !$authenticatorObj->getUserPresent()) {
            throw new WebAuthnException('user not present during authentication', WebAuthnException::USER_PRESENT);
        }

        // 13. If user verification is required for this assertion, verify that the User Verified bit of the flags in authData is set.
        if ($requireUserVerification && !$authenticatorObj->getUserVerified()) {
            throw new WebAuthnException('user not verificated during authentication', WebAuthnException::USER_VERIFICATED);
        }

        // 14. Verify the values of the client extension outputs
        //     (extensions not implemented)

        // 16. Using the credential public key looked up in step 3, verify that sig is a valid signature
        //     over the binary concatenation of authData and hash.
        $dataToVerify = '';
        $dataToVerify .= $authenticatorData;
        $dataToVerify .= $clientDataHash;

        if (!$this->_verifySignature($dataToVerify, $signature, $credentialPublicKey)) {
            throw new WebAuthnException('invalid signature', WebAuthnException::INVALID_SIGNATURE);
        }

        $signatureCounter = $authenticatorObj->getSignCount();
        if ($signatureCounter !== 0) {
            $this->_signatureCounter = $signatureCounter;
        }

        // 17. If either of the signature counter value authData.signCount or
        //     previous signature count is nonzero, and if authData.signCount
        //     less than or equal to previous signature count, it's a signal
        //     that the authenticator may be cloned
        if ($prevSignatureCnt !== null) {
            if ($signatureCounter !== 0 || $prevSignatureCnt !== 0) {
                if ($prevSignatureCnt >= $signatureCounter) {
                    throw new WebAuthnException('signature counter not valid', WebAuthnException::SIGNATURE_COUNTER);
                }
            }
        }

        return true;
    }

    /**
     * Downloads root certificates from FIDO Alliance Metadata Service (MDS) to a specific folder
     * https://fidoalliance.org/metadata/
     * @param string $certFolder Folder path to save the certificates in PEM format.
     * @param bool $deleteCerts delete certificates in the target folder before adding the new ones.
     * @return int number of cetificates
     * @throws WebAuthnException
     */
    public function queryFidoMetaDataService($certFolder, $deleteCerts=true) {
        $url = 'https://mds.fidoalliance.org/';
        $raw = null;
        if (\function_exists('curl_init')) {
            $ch = \curl_init($url);
            \curl_setopt($ch, CURLOPT_HEADER, false);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            \curl_setopt($ch, CURLOPT_USERAGENT, 'github.com/lbuchs/WebAuthn - A simple PHP WebAuthn server library');
            $raw = \curl_exec($ch);
            \curl_close($ch);
        } else {
            $raw = \file_get_contents($url);
        }

        $certFolder = \rtrim(\realpath($certFolder), '\\/');
        if (!is_dir($certFolder)) {
            throw new WebAuthnException('Invalid folder path for query FIDO Alliance Metadata Service');
        }

        if (!\is_string($raw)) {
            throw new WebAuthnException('Unable to query FIDO Alliance Metadata Service');
        }

        $jwt = \explode('.', $raw);
        if (\count($jwt) !== 3) {
            throw new WebAuthnException('Invalid JWT from FIDO Alliance Metadata Service');
        }

        if ($deleteCerts) {
            foreach (\scandir($certFolder) as $ca) {
                if (\substr($ca, -4) === '.pem') {
                    if (\unlink($certFolder . DIRECTORY_SEPARATOR . $ca) === false) {
                        throw new WebAuthnException('Cannot delete certs in folder for FIDO Alliance Metadata Service');
                    }
                }
            }
        }

        list($header, $payload, $hash) = $jwt;
        $payload = Binary\ByteBuffer::fromBase64Url($payload)->getJson();

        $count = 0;
        if (\is_object($payload) && \property_exists($payload, 'entries') && \is_array($payload->entries)) {
            foreach ($payload->entries as $entry) {
                if (\is_object($entry) && \property_exists($entry, 'metadataStatement') && \is_object($entry->metadataStatement)) {
                    $description = $entry->metadataStatement->description ?? null;
                    $attestationRootCertificates = $entry->metadataStatement->attestationRootCertificates ?? null;

                    if ($description && $attestationRootCertificates) {

                        // create filename
                        $certFilename = \preg_replace('/[^a-z0-9]/i', '_', $description);
                        $certFilename = \trim(\preg_replace('/\_{2,}/i', '_', $certFilename),'_') . '.pem';
                        $certFilename = \strtolower($certFilename);

                        // add certificate
                        $certContent = $description . "\n";
                        $certContent .= \str_repeat('-', \mb_strlen($description)) . "\n";

                        foreach ($attestationRootCertificates as $attestationRootCertificate) {
                            $attestationRootCertificate = \str_replace(["\n", "\r", ' '], '', \trim($attestationRootCertificate));
                            $count++;
                            $certContent .= "\n-----BEGIN CERTIFICATE-----\n";
                            $certContent .= \chunk_split($attestationRootCertificate, 64, "\n");
                            $certContent .= "-----END CERTIFICATE-----\n";
                        }

                        if (\file_put_contents($certFolder . DIRECTORY_SEPARATOR . $certFilename, $certContent) === false) {
                            throw new WebAuthnException('unable to save certificate from FIDO Alliance Metadata Service');
                        }
                    }
                }
            }
        }

        return $count;
    }

    // -----------------------------------------------
    // PRIVATE
    // -----------------------------------------------

    /**
     * checks if the origin matchs the RP ID
     * @param string $origin
     * @return boolean
     * @throws WebAuthnException
     */
    private function _checkOrigin($origin) {
        if (str_starts_with($origin, 'android:apk-key-hash:')) {
            return $this->_checkAndroidKeyHashes($origin);
        }

        // https://www.w3.org/TR/webauthn/#rp-id

        // The origin's scheme must be https
        if ($this->_rpId !== 'localhost' && \parse_url($origin, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        // extract host from origin
        $host = \parse_url($origin, PHP_URL_HOST);
        $host = \trim($host, '.');

        // The RP ID must be equal to the origin's effective domain, or a registrable
        // domain suffix of the origin's effective domain.
        return \preg_match('/' . \preg_quote($this->_rpId) . '$/i', $host) === 1;
    }

    /**
     * checks if the origin value contains a known android key hash
     * @param string $origin
     * @return boolean
     */
    private function _checkAndroidKeyHashes($origin) {
        $parts = explode('android:apk-key-hash:', $origin);
        if (count($parts) !== 2) {
            return false;
        }
        return in_array($parts[1], $this->_androidKeyHashes, true);
    }

    /**
     * generates a new challange
     * @param int $length
     * @return string
     * @throws WebAuthnException
     */
    private function _createChallenge($length = 32) {
        if (!$this->_challenge) {
            $this->_challenge = ByteBuffer::randomBuffer($length);
        }
        return $this->_challenge;
    }

    /**
     * check if the signature is valid.
     * @param string $dataToVerify
     * @param string $signature
     * @param string $credentialPublicKey PEM format
     * @return bool
     */
    private function _verifySignature($dataToVerify, $signature, $credentialPublicKey) {

        // Use Sodium to verify EdDSA 25519 as its not yet supported by openssl
        if (\function_exists('sodium_crypto_sign_verify_detached') && !\in_array('ed25519', \openssl_get_curve_names(), true)) {
            $pkParts = [];
            if (\preg_match('/BEGIN PUBLIC KEY\-+(?:\s|\n|\r)+([^\-]+)(?:\s|\n|\r)*\-+END PUBLIC KEY/i', $credentialPublicKey, $pkParts)) {
                $rawPk = \base64_decode($pkParts[1]);

                // 30        = der sequence
                // 2a        = length 42 byte
                // 30        = der sequence
                // 05        = lenght 5 byte
                // 06        = der OID
                // 03        = OID length 3 byte
                // 2b 65 70  = OID 1.3.101.112 curveEd25519 (EdDSA 25519 signature algorithm)
                // 03        = der bit string
                // 21        = length 33 byte
                // 00        = null padding
                // [...]     = 32 byte x-curve
                $okpPrefix = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

                if ($rawPk && \strlen($rawPk) === 44 && \substr($rawPk,0, \strlen($okpPrefix)) === $okpPrefix) {
                    $publicKeyXCurve = \substr($rawPk, \strlen($okpPrefix));

                    return \sodium_crypto_sign_verify_detached($signature, $dataToVerify, $publicKeyXCurve);
                }
            }
        }

        // verify with openSSL
        $publicKey = \openssl_pkey_get_public($credentialPublicKey);
        if ($publicKey === false) {
            throw new WebAuthnException('public key invalid', WebAuthnException::INVALID_PUBLIC_KEY);
        }

        return \openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
