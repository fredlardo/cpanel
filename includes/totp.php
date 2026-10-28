<?php
// TOTP RFC 6238 — no external dependency
function totp_base32_encode(string $data): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $out=''; $bits=0; $val=0;
    for($i=0;$i<strlen($data);$i++){ $val=($val<<8)|ord($data[$i]); $bits+=8; while($bits>=5){ $bits-=5; $out.=$alphabet[($val>>$bits)&31]; } }
    if($bits>0) $out.=$alphabet[($val<<(5-$bits))&31];
    return $out;
}
function totp_base32_decode(string $b32): string {
    $b32=strtoupper(str_replace('=','',trim($b32))); $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $out=''; $bits=0; $val=0;
    for($i=0;$i<strlen($b32);$i++){ $p=strpos($alphabet,$b32[$i]); if($p===false) continue; $val=($val<<5)|$p; $bits+=5; if($bits>=8){ $bits-=8; $out.=chr(($val>>$bits)&255); } }
    return $out;
}
function totp_generateSecret(int $len=20): string {
    return totp_base32_encode(random_bytes($len));
}
function totp_code(string $secret, ?int $time=null, int $step=30, int $digits=6): string {
    $counter=intdiv($time ?? time(), $step);
    $bin=pack('J',$counter); // 64-bit BE
    $hash=hash_hmac('sha1',$bin,totp_base32_decode($secret),true);
    $offset=ord($hash[19]) & 0x0F;
    $code=((ord($hash[$offset])&0x7F)<<24)|((ord($hash[$offset+1])&0xFF)<<16)|((ord($hash[$offset+2])&0xFF)<<8)|(ord($hash[$offset+3])&0xFF);
    return str_pad((string)($code % (10**$digits)), $digits, '0', STR_PAD_LEFT);
}
function totp_verify(string $secret, string $code, int $window=1, int $step=30): bool {
    $code=trim($code);
    if(!preg_match('/^\d{6}$/',$code)) return false;
    $t=time();
    for($i=-$window;$i<=$window;$i++){
        if(hash_equals(totp_code($secret,$t+$i*$step,$step), $code)) return true;
    }
    return false;
}
function totp_qr_url(string $email, string $secret, string $issuer='Doxera+'): string {
    $label=rawurlencode($issuer.':'.$email); $params=http_build_query(['secret'=>$secret,'issuer'=>$issuer]);
    // otpauth URI — used by Google Authenticator / Authy
    $otpauth="otpauth://totp/$label?$params";
    // QR via quickchart (no key) — fallback to api.qrserver
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.rawurlencode($otpauth);
}
