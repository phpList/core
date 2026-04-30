<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model;

enum BounceAction: string
{
    case BlacklistUser = 'blacklistuser';
    case BlacklistEmailAndDeleteBounce = 'blacklistemailanddeletebounce';
    case BlacklistEmail = 'blacklistemail';
    case BlacklistUserAndDeleteBounce = 'blacklistuseranddeletebounce';
    case DecreaseCountConfirmUserAndDeleteBounce = 'decreasecountconfirmuseranddeletebounce';
    case DeleteBounce = 'deletebounce';
    case DeleteUserAndBounce = 'deleteuserandbounce';
    case DeleteUser = 'deleteuser';
    case UnconfirmUserAndDeleteBounce = 'unconfirmuseranddeletebounce';
    case UnconfirmUser = 'unconfirmuser';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
