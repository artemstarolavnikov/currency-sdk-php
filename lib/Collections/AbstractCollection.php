<?php

namespace CurrencySDK\Collections;

class AbstractCollection
{
    /**
     * @var array
     */
    private $_items = array();

    protected $entity;

    public function __construct($response = array())
    {
        $this->_items = array();
        if ($this->entity){
            foreach ($response as $item) {
                $this->_items[] = new $this->entity($item);
            }
        }
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->_items;
    }
}