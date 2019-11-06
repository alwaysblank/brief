<?php


namespace AlwaysBlank\Brief;


class EmptyBrief extends Brief
{
    /**
     * EmptyBrief constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->parseSettings($settings);
    }
}