<?php

namespace CurrencySDK\Entity;

class Asset extends Entity
{
    /**
     * @var bool
     */
    public $can_deposit;

    /**
     * @var bool
     */
    public $can_withdraw;

    /**
     * @var string
     */
    public $description;

    /**
     * @var float
     */
    public $maker_fee;

    /**
     * @var float
     */
    public $max_withdraw;

    /**
     * @var float
     */
    public $min_withdraw;

    /**
     * @var string
     */
    public $name;

    /**
     * @var float
     */
    public $taker_fee;
}