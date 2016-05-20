<?php
abstract class Challenge {
	protected $totalChoice;
	protected $subChallenges;
	protected $valueType;
	protected $value;
	
	public function __construct($valueType, $value){
		$this->totalChoice = array(array(0,3));
		$this->subChallenges = array();
		$this->valueType = $valueType;
		$this->value = $value;
	}
	public function getValueType() {
		return $this->valueType;
	}
	public function getValue() {
		if ($this->valueType == self::$unknownType) {
			return $this;
		} else {
			return $this->value;
		}
	}
	abstract public function toString();

	public static function containsValidKey($str) {
		if (preg_match("/[A-Za-z_][A-Za-z_0-9]{0,10}/", $str, $match)) {
			if ($match[0] == $str) {
				return true;
			} else {
				// Another try
				$rndOffset = mt_rand(0, mb_strlen($str, 'utf-8') - 1);
				if (preg_match("/[A-Za-z_][A-Za-z_0-9]{0,10}/", $str, $match2, 0, $rndOffset)) {
					$offset = mb_strpos($str, $match2[0], $rndOffset, 'utf-8');
					if ($offset !== false) {
						$length = mb_strlen($match2[0], 'utf-8');
						return array($offset, $length, $match2[0]);
					}
				}
				$offset = mb_strpos($str, $match[0], 0, 'utf-8');
				$length = mb_strlen($match[0], 'utf-8');
				return array($offset, $length, $match[0]);
			}
		} else {
			return false;
		}
	}
	
	public function updateTotalChoice(&$suggestList) {
		$this->totalChoice = array();
		$choiceSum = array(0,3);
		foreach($suggestList as &$sug) {
			if ($sug->getValueType() == self::$stringType && self::containsValidKey($sug->getValue()) !== false) {
				$choiceSum[0] = $choiceSum[0] + 1;
			} elseif ($sug->getValueType() == self::$numberType) {
				$choiceSum[0] = $choiceSum[0] + 1;
			}
			unset($sug);
		}
		// may always use eval convert
		// may always retrieve from an array
		// may always retrieve from an object
		$this->totalChoice[] = $choiceSum;
		$t = $this->extraChoice($suggestList);
		$this->totalChoice[] = $t;
		$choiceSum[0] = $choiceSum[0] + $t[0];
		$choiceSum[1] = $choiceSum[1] + $t[1];
		for($index = 0; $index < count($this->subChallenges); $index++) {
			$t = $this->subChallenges[$index]->updateTotalChoice($suggestList);
			$this->totalChoice[] = $t;
			$choiceSum[0] = $choiceSum[0] + $t[0];
			$choiceSum[1] = $choiceSum[1] + $t[1];
		}
		return $choiceSum;
	}
	abstract protected function extraChoice(&$suggestList);
	abstract protected function convertSelf(&$suggestList, $useSuggest, $choiceIndex);
	public function convert(&$suggestList, $useSuggest, $choiceIndex) {
		$suggestIndex = $useSuggest ? 0 : 1;
		if ($choiceIndex < $this->totalChoice[0][$suggestIndex]) {
			if (!$useSuggest && $choiceIndex == 0) {
				return $this->evalConvert();
			} else {
				return $this->subsetConvert($suggestList, $useSuggest, $choiceIndex);
			}
		} elseif ($choiceIndex < $this->totalChoice[1][$suggestIndex] + $this->totalChoice[0][$suggestIndex]) {
			return $this->convertSelf($suggestList, $useSuggest, $choiceIndex - $this->totalChoice[0][$suggestIndex]);
		} else {
			$sum = $this->totalChoice[0][$suggestIndex] + $this->totalChoice[1][$suggestIndex];
			for($index = 0; $index < count($this->subChallenges); $index++) {
				$sum = $sum + $this->totalChoice[$index + 2][$suggestIndex];
				if ($choiceIndex < $sum) {
					$this->subChallenges[$index] = $this->subChallenges[$index]->convert($suggestList, $useSuggest,
						$choiceIndex - $sum + $this->totalChoice[$index + 2][$suggestIndex]);
					return $this;
				}
			}
			throw new Exception("No such choice");
		}
	}
	public static function randomKey($length) {
		$keyStr = "_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$tmpKey = "";
		for ($i = 0; $i < $length; $i += 4) {
			$rndData = mt_rand(0, 0xffffff);
			for($j = 0; $j < 4; $j ++) {
				$index = ($rndData & 63);
				$rndData = ($rndData >> 6);
				// This will produce more "_". But who cares...
				if ($index >= strlen($keyStr)) {
					$index = 0;
				} elseif ($tmpKey == "" && $index > 52) {
					$index = 0;
				}
				$tmpKey = $tmpKey . substr($keyStr, $index, 1);
			}
		}
		return substr($tmpKey, 0, $length);
	}
	protected function subsetConvert(&$suggestList, $useSuggest, $choiceIndex) {
		if ($useSuggest) {
			foreach($suggestList as $index => &$sug) {
				unset($sug);
				if ($suggestList[$index]->getValueType() == self::$stringType && self::containsValidKey($suggestList[$index]->getValue()) !== false) {
					if ($choiceIndex > 0) {
						$choiceIndex--;
						continue;
					}
					$validKey = self::containsValidKey($suggestList[$index]->getValue());
					if ($validKey === true) {
						$keyObj = $suggestList[$index];
						$keyValue = $suggestList[$index]->getValue();
					} else {
						$keyObj = new MethodChallenge($suggestList[$index], "substr", 
							array(
								new NumberChallenge($validKey[0]),
								new NumberChallenge($validKey[1])
							), self::$stringType, $validKey[2]);
						$keyValue = $validKey[2];
					}
					do{
						$otherKey = self::randomKey(strlen($keyValue));
					} while ($otherKey == $keyValue);
					$otherValue = self::randomKey(15);
					$keys = array($otherKey, $keyValue);
					shuffle($keys);
					$kvArray = array();
					foreach($keys as $k) {
						$kvArray[$k] = ($k == $keyValue) ? $this : (new StringChallenge($otherValue));
					}
					unset($suggestList[$index]);
					return new IndexChallenge(new ObjectChallenge($kvArray), $keyObj, $this->getValueType(), $this->getValue());
				} elseif ($suggestList[$index]->getValueType() == self::$numberType) {
					if ($choiceIndex > 0) {
						$choiceIndex--;
						continue;
					}
					if ($suggestList[$index]->getValue() < 0) {
						$indexValue = round(-$suggestList[$index]->getValue()) % 3;
						$indexObj = new ExpressionChallenge("%s%%3", array(
								new CallChallenge(new VariableChallenge("_b",self::functionType,"_b"),array(
										new ExpressionChallenge("-%s", array($suggestList[$index]),self::numberType,-$suggestList[$index]->getValue())
								),self::$numberType,round(-$suggestList[$index]->getValue()))
							), self::$numberType, $indexValue);
					} else {
						$indexValue = round($suggestList[$index]->getValue()) % 3;
						$indexObj = new ExpressionChallenge("%s%%3", array(
								new CallChallenge(new VariableChallenge("_b",self::functionType,"_b"),array(
										$suggestList[$index]
								),self::$numberType,round(-$suggestList[$index]->getValue()))
							), self::$numberType, $indexValue);
					}
					$objArrays = array();
					for($i = 0; $i < 3; $i ++) {
						$objArrays[] = ($i == $indexValue) ? $this : (new StringChallenge(self::randomKey(16)));
					}
					unset($suggestList[$index]);
					return new IndexChallenge(new ArrayChallenge($objArrays), $indexObj, $this->getValueType(), $this->getValue());
				}
			}
			throw new Exception("No such choice");
		} else {
			if ($choiceIndex == 1) {
				$keyValue = self::randomKey(7);
				do{
					$otherKey = self::randomKey(strlen($keyValue));
				} while ($otherKey == $keyValue);
				$otherValue = self::randomKey(15);
				$keys = array($otherKey, $keyValue);
				shuffle($keys);
				$kvArray = array();
				foreach($keys as $k) {
					$kvArray[$k] = ($k == $keyValue) ? $this : (new StringChallenge($otherValue));
				}
				return new PropertyChallenge(new ObjectChallenge($kvArray), $keyValue, $this->getValueType(), $this->getValue());				
			} else {
				$indexValue = mt_rand(0,2);
				$indexObj = new NumberChallenge($indexValue);
				$objArrays = array();
				for($i = 0; $i < 3; $i ++) {
					$objArrays[] = ($i == $indexValue) ? $this : (new StringChallenge(self::randomKey(16)));
				}
				return new IndexChallenge(new ArrayChallenge($objArrays), $indexObj, $this->getValueType(), $this->getValue());
			}
		}
	}
	protected function evalConvert() {
		// Convert to eval function
		return new CallChallenge(new VariableChallenge("_e", self::$functionType, "_e"), array(new StringChallenge($this->toString())),
			$this->valueType, $this->value);
	}
	protected function allSubStrings() {
		return array_map(function($obj){return $obj->toString();}, $this->subChallenges);
	}
	public static $stringType = "String";
	public static $numberType = "Number";
	public static $booleanType = "Boolean";
	public static $objectType = "Object";
	public static $arrayType = "Array";
	public static $functionType = "Function";
	public static $unknownType = "Unknown";
}

class VariableChallenge extends Challenge {
	protected $varName;
	public function __construct($varName, $valueType, $value) {
		parent::__construct($valueType, $value);
		$this->varName = $varName;
	}
	public function toString() {
		return $this->varName;
	}
	public function extraChoice(&$suggestList) {
		return array(0,0);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}
}

// property access (xxx.xxx)
class PropertyChallenge extends Challenge {
	protected $propertyName;
	public function __construct($obj, $propertyName, $valueType, $value) {
		parent::__construct($valueType, $value);
		$this->propertyName = $propertyName;
		$this->subChallenges = array($obj);
	}
	public function toString() {
		return $this->subChallenges[0]->toString() . "." . $this->propertyName;
	}
	public function extraChoice(&$suggestList) {
		// May convert to index
		$choice = 0;
		foreach($suggestList as &$suggest) {
			if ($suggest->getValueType() == Challenge::$stringType && $suggest->getValue() == $propertyName) {
				$choice = $choice + 1;
			}
			unset($suggest);
		}
		return array($choice * 3, 3);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		if ($useSuggest) {
			foreach($suggestList as $i => &$sug) {
				unset($sug);
				if ($suggestList[$i]->getValueType() == Challenge::$stringType && $suggestList[$i]->getValue() == $propertyName) {
					if ($choiceIndex >= 3) {
						$choiceIndex -= 3;
						continue;
					}
					$result = new IndexChallenge($this->subChallenges[0], $suggestList[$i], $this->getValueType(), $this->getValue());
					unset($suggestList[$i]);
					return $result;
				}
			}
			throw new Exception("No such choice");
		} else {
			return new IndexChallenge($this->subChallenges[0], new StringChallenge($this->propertyName), $this->getValueType(), $this->getValue());
		}
	}
}

// expressions ( xxx + xxx - xxx * xxx ... )
class ExpressionChallenge extends Challenge {
	protected $expression;
	public function __construct($expression, $objs, $valueType, $value) {
		parent::__construct($valueType, $value);
		$this->expression = $expression;
		$this->subChallenges = $objs;
	}
	public function toString() {
		if (count($this->subChallenges) == 0) {
			return "(".$expression.")";
		} else {
			return "(".vsprintf($expression, $this->allSubStrings()).")";
		}
	}
	public function extraChoice(&$suggestList) {
		return array(0,0);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}	
}

// Function call xxx(xxx,xxx,...)
class CallChallenge extends Challenge {
	public function __construct($funcObj, $arguments, $valueType, $value) {
		parent::__construct($valueType, $value);
		$this->subChallenges = array_merge(array($funcObj), $arguments);
	}
	public function toString() {
		$allSubs = $this->allSubStrings();
		$top = array_shift($allSubs);
		return $top."(".implode(",",$allSubs).")";
	}
	public function extraChoice(&$suggestList) {
		// May convert to _h, may convert to _i
		return array(0,10);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		if ($choiceIndex < 5) {
			return new CallChallenge(new VariableChallenge("_h", Challenge::$functionType, "_h"),
				array(
					$this->subChallenges[0],
					new ArrayChallenge(array_slice($this->subChallenges,1))
				), $this->valueType, $this->value);
		} else {
			return new CallChallenge(new CallChallenge(new VariableChallenge("_i", Challenge::$functionType, "_i"),
				array(
					$this->subChallenges[0],
					new VariableChallenge("window",Challenge::$unknownType,null)
				), Challenge::$functionType, ""),
				array(new ArrayChallenge(array_slice($this->subChallenges,1))),
				$this->valueType, $this->value);
		}
	}
}

// Function call with object xxx.xxx(xxx,xxx,xxx...)
class MethodChallenge extends Challenge {
	protected $functionName;
	public function __construct($thisObj, $functionName, $arguments, $valueType, $value) {
		parent::__construct($valueType, $value);
		$this->subChallenges = array_merge(array($thisObj), $arguments);
		$this->functionName = $functionName;
	}
	public function toString() {
		$allSubs = $this->allSubStrings();
		$thisObjExpr = array_shift($allSubs);
		return $thisObjExpr . "." . ($this->functionName) . "(" . implode(",",$allSubs) . ")";
	}
	public function extraChoice(&$suggestList) {
		// May convert to indexCall
		return array(0,5);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		return new IndexCallChallenge($this->subChallenges[0], new StringChallenge($this->functionName),
			array_slice($this->subChallenges,1), $this->valueType, $this->value);
	}
}

// xxx[xxx]
class IndexChallenge extends Challenge {
	public function __construct($thisObj, $indexObj, $valueType, $value) {
		parent::__construct($valueType, $value);
		$this->subChallenges = array($thisObj, $indexObj);
	}
	public function toString() {
		$allSubs = $this->allSubStrings();
		return $allSubs[0] . "[" . $allSubs[1] . "]";
	}
	public function extraChoice(&$suggestList) {
		// May convert to _g
		return array(0,5);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
			return new CallChallenge(new VariableChallenge("_g", Challenge::$functionType, "_g"),
				array(
					$this->subChallenges[0],
					$this->subChallenges[1]
				), $this->valueType, $this->value);
	}	
}

// xxx[xxx](xxx,xxx,...)
class IndexCallChallenge extends Challenge {
	public function __construct($thisObj, $indexObj, $arguments, $valueType, $value) {
		parent::__construct($valueType, $value);
		$this->subChallenges = array_merge(array($thisObj, $indexObj), $arguments);
	}
	public function toString() {
		$allSubs = $this->allSubStrings();
		$thisObjExpr = array_shift($allSubs);
		$indexObjExpr = array_shift($allSubs);
		return $thisObjExpr . "[" . $indexObjExpr . "](" . implode(",", $allSubs) . ")";
	}
	public function extraChoice(&$suggestList) {
		return array(0,0);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}
}


// Pure string
class StringChallenge extends Challenge {
	public function __construct($s){
		parent::__construct(Challenge::$stringType, $s);
	}
	public static function escapeString($str) {
		$noescape = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_";
		$noescapeUtf16 = mb_convert_encoding($noescape, 'UTF-16BE');
		// Javascript use UTF-16 to encode characters
		$utf16 = mb_convert_encoding($str, 'UTF-16BE', 'utf-8');
		$result = "";
		for($index = 0; $index < mb_strlen($utf16, 'UTF-16BE'); $index++) {
			$c = mb_substr($utf16, $index, 1, 'UTF-16BE');
			$pos = mb_strpos($noescapeUtf16, $c, 0, 'UTF-16BE');
			if ($pos === false) {
				for($i = 0; $i < strlen($c); $i = $i + 2) {
					$result = $result . "\\u" . strtolower(bin2hex(substr($c,$i,2)));
				}
			} else {
				$result = $result . substr($noescape, $pos, 1);
			}
		}
		return "\"" . $result . "\"";
	}
	public function toString() {
		return self::escapeString($this->value);
	}
	public function extraChoice(&$suggestList) {
		
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}
}

// Number
class NumberChallenge extends Challenge {
	public function __construct($number){
		parent::__construct(Challenge::$numberType, $number);
	}
	public function toString() {
		if ($this->getValue() < 0) {
			return "(".strval($this->getValue()).")";
		} else {
			return strval($this->getValue());
		}
	}
	public function extraChoice(&$suggestList) {
		return array(0,0);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}
}

// Boolean
class BooleanChallenge extends Challenge {
	public function __construct($bool){
		parent::__construct(Challenge::$booleanType, $bool);
	}
	public function toString() {
		return $this->value ? "true" : "false";
	}
	public function extraChoice(&$suggestList) {
		return array(0,0);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}
}

// Array [xxx, xxx, xxx]
class ArrayChallenge extends Challenge {
	public function __construct($array){
		parent::__construct(Challenge::$arrayType, array_map(function($obj){return $obj->getValue();}, $array));
		$this->subChallenges = $array;
	}
	public function toString() {
		$allSubs = $this->allSubStrings();
		return "[".implode(",",$allSubs)."]";
	}
	public function extraChoice(&$suggestList) {
		return array(0,0);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}	
}

// Object {aaa:bbb,ccc:ddd}
class ObjectChallenge extends Challenge {
	protected $keys;
	public function __construct($kvArray){
		parent::__construct(Challenge::$objectType, array_map(function($obj){return $obj->getValue();}, $kvArray));
		$this->keys = array_keys($kvArray);
		$this->subChallenges = array_values($kvArray);
	}
	public function toString() {
		$allSubs = $this->allSubStrings();
		return "({".implode(",",array_map(function($k,$v){return StringChallenge::escapeString($k).":".$v;},$this->keys, $allSubs))."})";
	}
	public function extraChoice(&$suggestList) {
		return array(0,0);
	}
	public function convertSelf(&$suggestList, $useSuggest, $choiceIndex) {
		throw new Exception("No such choice");
	}	
}

header('Content-Type: text/html;charset=utf-8');
$initValue = Challenge::randomKey(32);

$initObj = new StringChallenge($initValue);

$suggest = array(
	new PropertyChallenge(new VariableChallenge("navigator", Challenge::$unknownType, null), "userAgent", Challenge::$stringType, $_SERVER['HTTP_USER_AGENT']), new CallChallenge(
		new VariableChallenge("_m", Challenge::$functionType, "_m", Challenge::$functionType, "_m"),
		array(new PropertyChallenge(new VariableChallenge("navigator", Challenge::$unknownType, null), "userAgent", Challenge::$stringType, $_SERVER['HTTP_USER_AGENT'])), Challenge::$stringType, md5($_SERVER['HTTP_USER_AGENT']))
);


for($i = 0; $i < 20; $i++) {
	$counts = $initObj->updateTotalChoice($suggest);
	if ($counts[0] > 0) {
		$useSuggest = true;
		$choiceIndex = mt_rand(0, $counts[0] - 1);
	} else {
		$useSuggest = false;
		$choiceIndex = mt_rand(0, $counts[1] - 1);
	}
	$initObj = $initObj->convert($suggest, $useSuggest, $choiceIndex);
}


?>
<script type="text/javascript" src="js/challenge.js"></script>
<?php echo $initValue."<br/>"; ?>
<?php $js = $initObj->toString(); echo htmlentities($js, ENT_COMPAT,'utf-8') . "<br/>" ?>
<script type="text/javascript">
document.write(eval(<?php echo StringChallenge::escapeString($js); ?>));
</script>
