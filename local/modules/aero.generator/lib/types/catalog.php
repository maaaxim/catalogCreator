<?php
/**
 * Created by PhpStorm.
 * User: maxim
 * Date: 22.08.2017
 * Time: 23:25
 */

namespace Aero\Generator\Types;

use Bitrix\Iblock\TypeLanguageTable;
use Bitrix\Iblock\TypeTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

/**
 * Class Catalog
 * responsible for generating catalogs
 *
 * @package Aero\Generator\Types
 */
class Catalog implements Generateable
{
    /**
     * Order for plan
     */
    const ORDER = 1;

    /**
     * @var bool
     */
    private $hasSku;

    /**
     * @var int
     */
    private $catalogCount;

    /**
     * @var int
     */
    private $skuPerProduct;

    /**
     * @var int
     */
    private $iterator = 0;

    /**
     * Catalog constructor.
     */
    public function __construct(){
        // cfg @TODO we need only 1 catalog
        $this->catalogCount  = (int) Option::get("aero.generator", "types_catalog");
        $this->skuPerProduct = (int) Option::get("aero.generator", "sku_count");
        // need sku?
        if($this->skuPerProduct > 0)
            $this->hasSku = true;
        else
            $this->hasSku = false;
    }

    /**
     * @inheritdoc
     */
    function generate(){
        $this->includeModules();
        $this->makeTypes();
        $this->cleanCache();
        $this->makeIblocks();
    }

    /**
     * Include modules
     */
    private function includeModules(){
        if(!Loader::includeModule("catalog"))
            throw new Exception("Catalog module is not included!");
        if(!Loader::includeModule("iblock"))
            throw new Exception("Iblock module is not included!");
    }

    /**
     * Create iblock types
     */
    private function makeTypes(){
        for($i = 0; $i < $this->catalogCount; $i++){
            $id = "aero_generator_" . $i;
            $typesRes = TypeTable::getList(["filter" => ["ID" => $id]]);
            if(!$typeFields = $typesRes->fetch()){
                TypeTable::add(["ID" => $id]);
                TypeLanguageTable::add([
                    "IBLOCK_TYPE_ID" => $id,
                    "LANGUAGE_ID" => "en",
                    "NAME" => "AERO Generator #" . $i,
                    "SECTIONS_NAME" => "Section",
                    "ELEMENTS_NAME" => "Element"
                ]);
            }
        }
    }

    /**
     * Clean cache
     */
    private function cleanCache(){
        global $CACHE_MANAGER;
        $CACHE_MANAGER->CleanAll();
    }

    /**
     * Create iblock data
     */
    private function makeIblocks(){
        $typeRes = TypeTable::getList([
            "filter" => ["%=ID" => "aero%"],
            "select" => ["ID"]
        ]);
        while ($typeFields = $typeRes->fetch()){
            $catalogId = $this->makeCatalogIblock($typeFields["ID"]);
            $this->setUpCatalog($catalogId);
            if($this->hasSku === true){
                $skuId = $this->makeSkuIblock($typeFields["ID"]);
                $linkPropertyId = $this->linkSkuToCatlaog($skuId, $catalogId);
                $this->connectSkuToCatalog($skuId, $catalogId, $linkPropertyId);
            }
            $this->iterator++;
        }
    }

    /**
     * Creates iblock for catalog
     *
     * @param $iblockType
     * @return bool
     * @throws \Exception
     */
    private function makeCatalogIblock($iblockType){
        $ib = new \CIBlock;
        $arFields = [
            "ACTIVE" => "Y",
            "NAME" => "Catalog #" . $this->iterator,
            "CODE" => "catalog_" . $iblockType,
            "LIST_PAGE_URL" => "/catalog_" . $iblockType . "/", // @TODO real paths
            "DETAIL_PAGE_URL" => "catalog_" . $iblockType . "/",
            "IBLOCK_TYPE_ID" => $iblockType,
            "SITE_ID" => ["s1"], // @TODO check it
            "SORT" => 500
        ];
        $catalogId = $ib->Add($arFields);
        if($catalogId <= 0)
            throw new \Exception($ib->LAST_ERROR . " =>" .$iblockType . " error happened!");
        return $catalogId;
    }

    /**
     * Set up iblock as catalog
     *
     * @param $catalogId
     * @throws \Exception
     */
    private function setUpCatalog($catalogId){
        global $APPLICATION;
        $arFields = ['IBLOCK_ID' => $catalogId];
        $boolResult = \CCatalog::Add($arFields);
        if ($boolResult == false) {
            if ($ex = $APPLICATION->GetException())
                throw new \Exception($ex->GetString());
        }
    }

    /**
     * Creates iblock for sku
     *
     * @param $iblockType
     * @return bool
     * @throws \Exception
     */
    private function makeSkuIblock($iblockType){
        $ib = new \CIBlock;
        $arFields = [
            "ACTIVE" => "Y",
            "NAME" => "Sku #" . $this->iterator,
            "CODE" => "sku_" . $iblockType,
            "IBLOCK_TYPE_ID" => $iblockType,
            "SITE_ID" => ["s1"], // @TODO get from existing site
            "SORT" => 500
        ];
        $skuId = $ib->Add($arFields);
        if($skuId <= 0)
            throw new \Exception($ib->LAST_ERROR . " error happened!");
        return $skuId;
    }

    /**
     * Add property link to catalog
     *
     * @param $skuId
     * @param $catalogId
     * @return bool
     */
    private function linkSkuToCatlaog($skuId, $catalogId){
        $arFields = [
            "NAME" => "Catalog element id",
            "ACTIVE" => "Y",
            "SORT" => "500",
            "CODE" => "CML2_LINK",
            "PROPERTY_TYPE" => "E",
            "IBLOCK_ID" => $skuId,
            "LINK_IBLOCK_ID" => $catalogId
        ];
        $ibp = new \CIBlockProperty;
        $linkPropertyId = $ibp->Add($arFields);
        return $linkPropertyId;
    }

    /**
     * Connect sku iblock to catalog
     *
     * @param $skuId
     * @param $catalogId
     * @param $linkPropertyId
     * @throws \Exception
     */
    private function connectSkuToCatalog($skuId, $catalogId, $linkPropertyId){
        global $APPLICATION;
        $arFields = [
            'IBLOCK_ID' => $skuId,
            'PRODUCT_IBLOCK_ID' => $catalogId,
            'SKU_PROPERTY_ID' => $linkPropertyId
        ];
        $boolResult = \CCatalog::Add($arFields);
        if ($boolResult == false) {
            if ($ex = $APPLICATION->GetException()) {
                if ($ex = $APPLICATION->GetException())
                    throw new \Exception($ex->GetString());
            }
        }
    }
}