<?php

namespace CurrencySDK\Entity;

class Entity
{
    /**
     * @param $data
     */
    public function __construct($data)
    {
        foreach ($data as $key => $value){
            if (property_exists($this, $key)){
                $this->{$key} = $value;
            }
        }
    }
}