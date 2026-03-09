<?php

namespace KrugerDavid\Bancard\Facades;

use Illuminate\Support\Facades\Facade;
use KrugerDavid\Bancard\BancardManager;

/**
 * @method static array singleBuy(int $shopProcessId, string $amount, string $description, string $returnUrl, array $options = [])
 * @method static array singleBuyZimple(int $shopProcessId, string $amount, string $description, string $returnUrl, string $userPhone, array $options = [])
 * @method static array rollback(int|string $shopProcessId)
 * @method static array getConfirmation(int|string $shopProcessId)
 * @method static array cardsNew(int $cardId, int $userId, string $userCellPhone, string $userMail, string $returnUrl)
 * @method static array usersCards(int $userId, array $options = [])
 * @method static array charge(int $shopProcessId, string $amount, string $aliasToken, array $options = [])
 * @method static array deleteCard(int $userId, string $aliasToken)
 * @method static string getCheckoutScriptUrl()
 * @method static string getCheckoutUrl()
 *
 * @see \KrugerDavid\Bancard\BancardManager
 */
class Bancard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BancardManager::class;
    }
}
