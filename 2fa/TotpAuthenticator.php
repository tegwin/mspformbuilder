<?php
class TotpAuthenticator {
    public function getSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    public function getOtpAuthUrl($user, $issuer, $secret) {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $user) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    }

    public function verifyCode($secret, $code, $window = 1) {
        $timeSlice = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $calc = $this->getCode($secret, $timeSlice + $i);
            if ($calc === $code) {
                return true;
            }
        }
        return false;
    }

    private function base32Decode($secret) {
        if (empty($secret)) return '';
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = [6, 4, 3, 1, 0];
        if (!in_array($paddingCharCount, $allowedValues)) return false;
        $secret = str_replace('=', '', $secret);
        $binaryString = '';
        for ($i = 0; $i < strlen($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], str_split($base32chars))) return false;
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert($base32charsFlipped[$secret[$i + $j] ?? 'A'], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            foreach ($eightBits as $char) {
                $binaryString .= chr(bindec($char));
            }
        }
        return $binaryString;
    }

    private function getCode($secret, $timeSlice) {
        $secretkey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $secretkey, true);
        $offset = ord($hm[19]) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
    }
}
