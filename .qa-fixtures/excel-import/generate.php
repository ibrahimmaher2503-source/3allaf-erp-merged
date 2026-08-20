<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Creator\WriterFactory;

$directory = __DIR__;
$write = static function (string $name, array $headers, array $row) use ($directory): void {
    $writer = WriterFactory::createFromFile($path = $directory.'/'.$name.'.xlsx');
    $writer->openToFile($path); $writer->addRow(Row::fromValues($headers)); $writer->addRow(Row::fromValues($row)); $writer->close();
};
$headers = ['code', 'name_ar', 'name_en', 'parent_code', 'status', 'sort_order'];
$write('category', $headers, ['QA-TOYS', 'ألعاب اختبار', 'QA Toys', '', 'active', 1]);
foreach (['brand' => ['QA-BRAND', 'علامة اختبار', 'QA Brand'], 'age' => ['QA-AGE', 'عمر اختبار', 'QA Age'], 'character' => ['QA-CHAR', 'شخصية اختبار', 'QA Character'], 'colour' => ['QA-COLOUR', 'لون اختبار', 'QA Colour'], 'gender' => ['QA-GENDER', 'جنس اختبار', 'QA Gender']] as $name => $values) $write($name, $headers, [...$values, '', 'active', 1]);
$productHeaders = ['item_code', 'name_ar', 'name_en', 'category_code', 'brand_code', 'age_code', 'character_code', 'colour_code', 'gender_code', 'preferred_supplier_code', 'status'];
$write('supplier', ['code', 'name_ar', 'name_en', 'contact_name', 'email', 'phone', 'tax_number', 'payment_terms', 'address', 'status', 'supplier_group_ar', 'supplier_group_en'], ['QA-SUP', 'مورد اختبار', 'QA Supplier', 'QA Contact', 'qa-supplier@example.test', '+201000000000', '', 'Cash', 'QA only', 'active', '', '']);
$write('product', $productHeaders, ['QA-PROD-001', 'منتج اختبار', 'QA Product', 'QA-TOYS', 'QA-BRAND', 'QA-AGE', 'QA-CHAR', 'QA-COLOUR', 'QA-GENDER', 'QA-SUP', 'active']);
