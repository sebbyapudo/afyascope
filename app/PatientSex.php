<?php

namespace App;

enum PatientSex: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';
}
