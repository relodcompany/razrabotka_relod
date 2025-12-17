<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

TSolution\Extensions::init(['item-action', 'notice', 'contacts', 'tabs.history']);
TSolution\Popover\Tooltip::initExtensions();
TSolution\Popover\OrderStatus::initExtensions();
