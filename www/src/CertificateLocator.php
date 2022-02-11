<?php

declare(strict_types=1);

namespace App;

class CertificateLocator
{
    public static function findCertificate(): Certificate
    {
        $certBase = '/etc/nginx/certs';

        $generatedKey = $certBase . '/ssl.key';
        $generatedCert = $certBase . '/ssl.crt';
        if (file_exists($generatedKey) && file_exists($generatedCert)) {
            return new Certificate($generatedKey, $generatedCert);
        }

        return new Certificate($certBase . '/default.key', $certBase . '/default.crt');
    }
}
