<?php

namespace App;

enum AsaClassification: string
{
    case One = 'I';
    case Two = 'II';
    case Three = 'III';
    case Four = 'IV';
    case Five = 'V';
    case Six = 'VI';

    public function displayName(): string
    {
        return "ASA {$this->value}";
    }
}
