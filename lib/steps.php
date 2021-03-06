<?php

namespace Catalog\Generator;

use Catalog\Generator\Entity\GeneratorTable;
use Bitrix\Main\Entity\ExpressionField;
use Catalog\Generator\Types\Product;
use Catalog\Generator\Exception as GeneratorException;

/**
 * Class Steps
 * responsible for making steps according to plan from db
 *
 * @package Catalog\Generator
 */
class Steps
{
    /**
     * Path to pics
     */
    const MODULE_IMG_PATH = '/bitrix/modules/catalog.generator/images/';

    /**
     * Iblock type code
     */
    const IBLOCK_TYPE = "catalog_generator";

    /**
     * Module name
     */
    const MODULE_NAME = "catalog.generator";

    /**
     * @var current step
     */
    protected $step;

    /**
     * @var step size
     */
    protected $stepSize;

    /**
     * @var step count
     */
    protected $stepCount;

    /**
     * @var type of generateable
     */
    protected $type;

    /**
     * @var step id
     */
    protected $id;

    /**
     * @var
     */
    protected $error = "";

    public function __construct()
    {
        $this->setCount();
        // $this->cleanSteps(); die();
    }

    /**
     * Makes a generation step.
     * Returns count of completed steps.
     * Returns 0 if there is nothing to do.
     *
     * @return int
     * @throws Exception
     */
    public function createNext()
    {
        try {
            if ($this->initStep()) {
                $this->stepSize = $this->type->getCountToGenerate();
                for($i = 0; $i < $this->stepSize; $i++)
                    $this->type->generate();
                $this->finish();
            } else {
                return 0;
            }
        } catch (GeneratorException $exception) {
            $this->error = $exception;
            return 0;
        }
        return $this->stepSize;
    }

    /**
     * Returns total steps count
     *
     * @return int
     */
    public function getCount()
    {
        return $this->stepCount;
    }

    /**
     * Returns current step
     *
     * @return int
     */
    public function getCurrent()
    {
        return $this->step;
    }

    /**
     * Initializes fields for step
     *
     * @return bool
     */
    private function initStep():bool
    {
        $inProgress = true;
        $stepRes = GeneratorTable::getList([
            "order" => ["STATUS" => "ASC", "ID" => "ASC"],
            "select" => ["ID", "STEP", "STATUS", "ITEMS_PER_STEP"],
            "limit" => 1
        ]);
        $lastItem = $stepRes->fetch();
        // Finish
        if($lastItem["STATUS"] == 1){
            $inProgress = false;
        } else {
            if ($lastItem == false) {
                $this->firstStep();
            } else {
                // Go step
                $this->step     = (int) $lastItem["STEP"];
                $this->id       = (int) $lastItem["ID"];
                // $this->stepSize = (int) $lastItem["ITEMS_PER_STEP"];
                $this->type     = new Product();
            }
        }
        return $inProgress;
    }

    /**
     * First step:
     * 1. Generates db structure
     * 2. Generates products generation plan
     * 3. Initiates first step
     */
    public function firstStep()
    {
        // Gen structure
        $structure = Plan::getSteps();
        foreach($structure as $part){
            $partObject = new $part();
            for($i = 0; $i < $partObject->getCountToGenerate(); $i++)
                $partObject->generate();
        }

        // Gen products plan
        $plan = new Plan();
        $plan->initProductsPlan();

        // Job first plan step
        $this->setCount();
        $this->initStep();
    }

    /**
     * Update step in db
     *
     * @throws \Exception
     */
    private function finish()
    {
        $result = GeneratorTable::update($this->id, [
            "STATUS" => 1,
        ]);
        if (!$result->isSuccess()){
            throw new ($result->getErrorMessages());
        }
    }

    /**
     * Clean table
     */
    private function cleanSteps()
    {
        $stepRes = GeneratorTable::getList([
            "select" => ["ID"]
        ]);
        while($stepFields = $stepRes->fetch())
            GeneratorTable::delete($stepFields["ID"]);
    }

    /**
     * Returns total steps quantity
     *
     * @return int
     */
    private function setCount()
    {
        $cntRes = GeneratorTable::getList([
            'select' => ['CNT'],
            'runtime' => [
                new ExpressionField('CNT', 'COUNT(*)')
            ]
        ]);
        $result = $cntRes->fetch();
        $this->stepCount = (int) $result["CNT"];
    }

    /**
     * @return array
     */
    public function getError()
    {
        return $this->error;
    }
}