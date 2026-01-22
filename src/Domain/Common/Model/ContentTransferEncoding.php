<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model;

enum ContentTransferEncoding: string
{
    case SevenBit = '7bit';
    case EightBit = '8bit';
    case Base64 = 'base64';
    case QuotedPrintable = 'quoted-printable';
    case Binary = 'binary';
}
