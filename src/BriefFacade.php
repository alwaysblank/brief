<?php


namespace AlwaysBlank\Brief;


use Illuminate\Support\Facades\Facade;

class BriefFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'brief';
    }
}