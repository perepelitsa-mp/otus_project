<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

CBitrixComponent::includeComponentClass('garage:car.list');

$component = new GarageCarListComponent($this);
$component->executeComponent();
