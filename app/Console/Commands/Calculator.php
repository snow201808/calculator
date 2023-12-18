<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use mysql_xdevapi\Exception;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

class Calculator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculator {--M|model=common : Choose the calculation method, with options [ simple ] or [ common ]} {--T|test : is test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'calculator';

    public string $model;
    public bool $isTest;
    protected array $operateTree;
    public string $expression;

    protected string $tagLeft = '(';
    protected string $tagRight = ')';
    protected string $operateAdd = '+';
    protected string $operateReduce = '-';
    protected string $operateRide = '*';
    protected string $operateDivision = '/';
    protected string $operateComplementation = '%';
    protected array $priorityList;
    protected array $commonList;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->model = $this->option("model");
        $this->isTest = $this->option("test");
        $this->init();
    }

    /**
     * init
     */
    public function init()
    {
        $this->waitInput();
        $this->calculator();
    }

    /**
     * Waiting for user input
     */
    public function waitInput()
    {
        // user input
        $expression = text(
            label: "Please enter the calculation expression",
            placeholder: "E.g. 1+1",
            required: true
        );
        // remove spaces
        $this->expression = str_replace(" ","", $expression);
        // check user input
        if (!$this->checkInput()) {
            $this->waitInput();
        }
    }

    /**
     * check user input
     */
    public function checkInput() : bool
    {
        $pattern = '/[^\d\.\+\-\*\/\%\(\)]/';
        preg_match($pattern, $this->expression, $match);
        if (empty($match)) {
            return true;
        }
        error("Contains illegal characters '".implode(",", $match)."', Only integers, decimals,+- */%, and () can be entered");
        return false;
    }

    /**
     * calculator
     */
    public function calculator()
    {
        try{
            $result = match ($this->model) {
                'simple' => eval("return $this->expression;"),
                'common' => $this->commonCalculator(),
                default => '',
            };
            //info("The calculation result is $result");
            $this->line("The calculation result is $result");
        } catch(\Exception $e) {
            error($e->getMessage());
        }
        if (!$this->isTest) {
            $this->init();
        }
    }

    /**
     * common Calculator
     */
    public function commonCalculator() : float
    {
        $tree = $this->ParseToBracketTree();
        $this->priorityList = $tree['priority'];
        unset($tree['priority']);
        // main tree
        $this->commonList = $tree;
        return $this->calculatorTree();
    }

    /**
     * parse bracket to tree
     */
    public function ParseToBracketTree() : array
    {
        // check is in ()
        $deep = 0;
        // deepNum
        $deepSeat = 0;
        $expressionList = str_split($this->expression);
        // operation tree
        $tree = [];
        $tree['priority'] = [];
        // with ()
        $mustInDeep = false;
        // determine after TagLeft
        $isAfterTagLeft = false;
        $outSeat = false;
        for ($i = 0; $i < count($expressionList); $i++) {
            // if start with )+-*/% ,ignore
            if ($i == 0 && !is_numeric($expressionList[$i]) && ($expressionList[$i] != $this->tagLeft)) {
                continue;
            }
            // after (, operators cannot be used
            if ($isAfterTagLeft && in_array($expressionList[$i], [$this->operateAdd,$this->operateReduce,$this->operateRide,$this->operateDivision,$this->operateComplementation])) {
                continue;
            }
            // (
            if ($expressionList[$i] == $this->tagLeft) {
                if ($deep != 0) {
                    $oldSeatName = "seat_".$deepSeat;
                    $deepSeat++;
                    $newSeatName = "seat_".$deepSeat;
                    $tree['priority'][$oldSeatName]['seat_'.$i] = $newSeatName;
                } else {
                    $deepSeat++;
                    $seatName = "seat_".$deepSeat;
                    $tree['seat_'.$i] = $seatName;
                    $tree['priority'][$seatName] = [];
                }
                $deep++;
                $isAfterTagLeft = true;
            } else if ($expressionList[$i] == $this->tagRight) {
                // )
                $isAfterTagLeft = false;
                $deep--;
                $outSeat = true;
            } else if (is_numeric($expressionList[$i])) {
                $isAfterTagLeft = false;
                if ($outSeat) {
                    $newDeepSeat = $deepSeat - 1;
                } else {
                    $newDeepSeat = $deepSeat;
                }
                $seatName = "seat_".$newDeepSeat;
                // numeric
                if ($mustInDeep) {
                    $tree['priority'][$seatName]['num_'.$i] = $expressionList[$i];
                } else {
                    $tree['num_'.$i] = $expressionList[$i];
                }
                // +-
            } else if (in_array($expressionList[$i], [$this->operateAdd,$this->operateReduce,$this->operateRide,$this->operateDivision,$this->operateComplementation])) {
                $isAfterTagLeft = false;
                if ($outSeat) {
                    $newDeepSeat = $deepSeat - 1;
                } else {
                    $newDeepSeat = $deepSeat;
                }
                $seatName = "seat_".$newDeepSeat;
                if ($mustInDeep) {
                    $tree['priority'][$seatName]['operation_'.$i] = $expressionList[$i];
                } else {
                    $tree['operation_'.$i] = $expressionList[$i];
                }
            }
            if ($deep != 0) {
                $mustInDeep = true;
            } else {
                $mustInDeep = false;
                $outSeat = false;
            }
        }
        return $tree;
    }

    /**
     * calculator tree
     */
    public function calculatorTree() : float
    {
        // calculator seat
        foreach ($this->commonList as $k => $v) {
            if (str_contains($v, 'seat')) {
                $this->commonList[$k] = $this->calculatorSeat($v);
            }
        }
        return $this->calculatorList($this->commonList);
    }

    /**
     * calculator seat
     */
    public function calculatorSeat($seat) : float
    {
        if (empty($this->priorityList[$seat])) {
            return 0;
        }
        foreach ($this->priorityList[$seat] as $key => $value ) {
            if (str_contains($value, 'seat')) {
                $this->priorityList[$seat][$key] = $this->calculatorSeat($this->priorityList[$seat][$key]);
            }
        }
        return $this->calculatorList($this->priorityList[$seat]);
    }

    /**
     * calculator list
     */
    public function calculatorList($list) : float
    {
        $stepOne = $this->calculatorRDC($list);
        return $this->calculatorARE($stepOne);
    }

    /**
     * calculator * / %
     */
    public function calculatorRDC($list) : array
    {
        // Calculate first * / %
        $canReturn = true;
        $list = array_values($list);
        foreach ($list as $listKey => $listValue) {
            if (in_array($listValue, [$this->operateRide,$this->operateDivision,$this->operateComplementation])){
                $previousKey = $listKey - 1;
                $previousValue = $list[$previousKey];
                $nextKey = $listKey + 1;
                $nextValue = $list[$nextKey];
                switch ($listValue) {
                    case $this->operateRide:
                        $list[$listKey] = $previousValue * $nextValue;
                        break;
                    case $this->operateDivision:
                        if ($nextValue == 0) {
                            throw new Exception('The divisor cannot be 0');
                        }
                        $list[$listKey] = $previousValue / $nextValue;
                        break;
                    case $this->operateComplementation:
                        $list[$listKey] = $previousValue % $nextValue;
                        break;
                }
                unset($list[$previousKey]);
                unset($list[$nextKey]);
                $canReturn = false;
                break;
            }
        }
        if (!$canReturn) {
            return $this->calculatorRDC($list);
        }
        return $list;
    }

    /**
     * calculator +-
     */
    public function calculatorARE($list) : float
    {
        $list = array_values($list);
        foreach ($list as $ck => $cv) {
            if (in_array($cv, [$this->operateAdd,$this->operateReduce])){
                $previousKey = $ck - 1;
                $previousValue = $list[$previousKey];
                $nextKey = $ck + 1;
                $nextValue = $list[$nextKey];
                switch ($cv) {
                    case $this->operateAdd:
                        $list[$ck] = $previousValue + $nextValue;
                        break;
                    case $this->operateReduce:
                        $list[$ck] = $previousValue - $nextValue;
                        break;
                }
                unset($list[$previousKey]);
                unset($list[$nextKey]);
                break;
            }
        }
        if (count($list) != 1) {
            $this->calculatorARE($list);
        }
        $list = array_values($list);
        return $list[0];
    }
}
