<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_InputFilter
 */

namespace ZendTest\InputFilter;

use PHPUnit_Framework_TestCase as TestCase;
use stdClass;
use Zend\InputFilter\Input;
use Zend\InputFilter\FileInput;
use Zend\InputFilter\BaseInputFilter as InputFilter;
use Zend\Filter;
use Zend\Validator;

class BaseInputFilterTest extends TestCase
{
    public function testInputFilterIsEmptyByDefault()
    {
        $filter = new InputFilter();
        $this->assertEquals(0, count($filter));
    }

    public function testAddingInputsIncreasesCountOfFilter()
    {
        $filter = new InputFilter();
        $foo    = new Input('foo');
        $filter->add($foo);
        $this->assertEquals(1, count($filter));
        $bar    = new Input('bar');
        $filter->add($bar);
        $this->assertEquals(2, count($filter));
    }

    public function testAddingInputWithNameDoesNotInjectNameInInput()
    {
        $filter = new InputFilter();
        $foo    = new Input('foo');
        $filter->add($foo, 'bar');
        $test   = $filter->get('bar');
        $this->assertSame($foo, $test);
        $this->assertEquals('foo', $foo->getName());
    }

    public function testCanAddInputFilterAsInput()
    {
        $parent = new InputFilter();
        $child  = new InputFilter();
        $parent->add($child, 'child');
        $this->assertEquals(1, count($parent));
        $this->assertSame($child, $parent->get('child'));
    }

    public function testCanRemoveInputFilter()
    {
        $parent = new InputFilter();
        $child  = new InputFilter();
        $parent->add($child, 'child');
        $this->assertEquals(1, count($parent));
        $this->assertSame($child, $parent->get('child'));
        $parent->remove('child');
        $this->assertEquals(0, count($parent));
    }

    public function getInputFilter()
    {
        $filter = new InputFilter();

        $foo = new Input();
        $foo->getFilterChain()->attachByName('stringtrim')
                              ->attachByName('alpha');
        $foo->getValidatorChain()->attach(new Validator\StringLength(3, 6));

        $bar = new Input();
        $bar->getFilterChain()->attachByName('stringtrim');
        $bar->getValidatorChain()->attach(new Validator\Digits());

        $baz = new Input();
        $baz->setRequired(false);
        $baz->getFilterChain()->attachByName('stringtrim');
        $baz->getValidatorChain()->attach(new Validator\StringLength(1, 6));

        $qux = new Input();
        $qux->setAllowEmpty(true);
        $qux->getFilterChain()->attachByName('stringtrim');
        $qux->getValidatorChain()->attach(new Validator\StringLength(5, 6));

        $filter->add($foo, 'foo')
               ->add($bar, 'bar')
               ->add($baz, 'baz')
               ->add($qux, 'qux')
               ->add($this->getChildInputFilter(), 'nest');

        return $filter;
    }

    public function getChildInputFilter()
    {
        $filter = new InputFilter();

        $foo = new Input();
        $foo->getFilterChain()->attachByName('stringtrim')
                              ->attachByName('alpha');
        $foo->getValidatorChain()->attach(new Validator\StringLength(3, 6));

        $bar = new Input();
        $bar->getFilterChain()->attachByName('stringtrim');
        $bar->getValidatorChain()->attach(new Validator\Digits());

        $baz = new Input();
        $baz->setRequired(false);
        $baz->getFilterChain()->attachByName('stringtrim');
        $baz->getValidatorChain()->attach(new Validator\StringLength(1, 6));

        $filter->add($foo, 'foo')
               ->add($bar, 'bar')
               ->add($baz, 'baz');
        return $filter;
    }

    public function dataSets()
    {
        return array(
            'valid-with-empty-and-null' => array(
                array(
                    'foo' => ' bazbat ',
                    'bar' => '12345',
                    'baz' => null,
                    'qux' => '',
                    'nest' => array(
                        'foo' => ' bazbat ',
                        'bar' => '12345',
                        'baz' => null,
                    ),
                ),
                true,
            ),
            'valid-with-empty' => array(
                array(
                    'foo' => ' bazbat ',
                    'bar' => '12345',
                    'qux' => '',
                    'nest' => array(
                        'foo' => ' bazbat ',
                        'bar' => '12345',
                    ),
                ),
                true,
            ),
            'invalid-with-empty-and-missing' => array(
                array(
                    'foo' => ' bazbat ',
                    'bar' => '12345',
                    'baz' => 'thisistoolong',
                    'nest' => array(
                        'foo' => ' bazbat ',
                        'bar' => '12345',
                        'baz' => 'thisistoolong',
                    ),
                ),
                false,
            ),
            'invalid-with-empty' => array(
                array(
                    'foo' => ' baz bat ',
                    'bar' => 'abc45',
                    'baz' => ' ',
                    'qux' => ' ',
                    'nest' => array(
                        'foo' => ' baz bat ',
                        'bar' => '123ab',
                        'baz' => ' ',
                    ),
                ),
                false,
            ),
        );
    }

    /**
     * @dataProvider dataSets
     * @group fmlife
     */
    public function testCanValidateEntireDataset($dataset, $expected)
    {
        $filter = $this->getInputFilter();
        $filter->setData($dataset);
        $this->assertSame($expected, $filter->isValid());
    }

    public function testCanValidatePartialDataset()
    {
        $filter = $this->getInputFilter();
        $validData = array(
            'foo' => ' bazbat ',
            'bar' => '12345',
        );
        $filter->setValidationGroup('foo', 'bar');
        $filter->setData($validData);
        $this->assertTrue($filter->isValid());

        $invalidData = array(
            'bar' => 'abc45',
            'nest' => array(
                'foo' => ' 123bat ',
                'bar' => '123ab',
            ),
        );
        $filter->setValidationGroup('bar', 'nest');
        $filter->setData($invalidData);
        $this->assertFalse($filter->isValid());
    }

    public function testCanRetrieveInvalidInputsOnFailedValidation()
    {
        $filter = $this->getInputFilter();
        $invalidData = array(
            'foo' => ' bazbat ',
            'bar' => 'abc45',
            'nest' => array(
                'foo' => ' baz bat boo ',
                'bar' => '12345',
            ),
        );
        $filter->setData($invalidData);
        $this->assertFalse($filter->isValid());
        $invalidInputs = $filter->getInvalidInput();
        $this->assertArrayNotHasKey('foo', $invalidInputs);
        $this->assertArrayHasKey('bar', $invalidInputs);
        $this->assertInstanceOf('Zend\InputFilter\Input', $invalidInputs['bar']);
        $this->assertArrayHasKey('nest', $invalidInputs/*, var_export($invalidInputs, 1)*/);
        $this->assertInstanceOf('Zend\InputFilter\InputFilterInterface', $invalidInputs['nest']);
        $nestInvalids = $invalidInputs['nest']->getInvalidInput();
        $this->assertArrayHasKey('foo', $nestInvalids);
        $this->assertInstanceOf('Zend\InputFilter\Input', $nestInvalids['foo']);
        $this->assertArrayNotHasKey('bar', $nestInvalids);
    }

    public function testCanRetrieveValidInputsOnFailedValidation()
    {
        $filter = $this->getInputFilter();
        $invalidData = array(
            'foo' => ' bazbat ',
            'bar' => 'abc45',
            'nest' => array(
                'foo' => ' baz bat ',
                'bar' => '12345',
            ),
        );
        $filter->setData($invalidData);
        $this->assertFalse($filter->isValid());
        $validInputs = $filter->getValidInput();
        $this->assertArrayHasKey('foo', $validInputs);
        $this->assertInstanceOf('Zend\InputFilter\Input', $validInputs['foo']);
        $this->assertArrayNotHasKey('bar', $validInputs);
        $this->assertArrayHasKey('nest', $validInputs);
        $this->assertInstanceOf('Zend\InputFilter\InputFilterInterface', $validInputs['nest']);
        $nestValids = $validInputs['nest']->getValidInput();
        $this->assertArrayHasKey('foo', $nestValids);
        $this->assertInstanceOf('Zend\InputFilter\Input', $nestValids['foo']);
        $this->assertArrayHasKey('bar', $nestValids);
        $this->assertInstanceOf('Zend\InputFilter\Input', $nestValids['bar']);
    }

    public function testValuesRetrievedAreFiltered()
    {
        $filter = $this->getInputFilter();
        $validData = array(
            'foo' => ' bazbat ',
            'bar' => '12345',
            'qux' => '',
            'nest' => array(
                'foo' => ' bazbat ',
                'bar' => '12345',
            ),
        );
        $filter->setData($validData);
        $this->assertTrue($filter->isValid());
        $expected = array(
            'foo' => 'bazbat',
            'bar' => '12345',
            'baz' => null,
            'qux' => '',
            'nest' => array(
                'foo' => 'bazbat',
                'bar' => '12345',
                'baz' => null,
            ),
        );
        $this->assertEquals($expected, $filter->getValues());
    }

    public function testCanGetRawInputValues()
    {
        $filter = $this->getInputFilter();
        $validData = array(
            'foo' => ' bazbat ',
            'bar' => '12345',
            'baz' => null,
            'qux' => '',
            'nest' => array(
                'foo' => ' bazbat ',
                'bar' => '12345',
                'baz' => null,
            ),
        );
        $filter->setData($validData);
        $this->assertTrue($filter->isValid());
        $this->assertEquals($validData, $filter->getRawValues());
    }

    public function testCanGetValidationMessages()
    {
        $filter = $this->getInputFilter();
        $filter->get('baz')->setRequired(true);
        $filter->get('nest')->get('baz')->setRequired(true);
        $invalidData = array(
            'foo' => ' bazbat boo ',
            'bar' => 'abc45',
            'baz' => '',
            'nest' => array(
                'foo' => ' baz bat boo ',
                'bar' => '123yz',
                'baz' => '',
            ),
        );
        $filter->setData($invalidData);
        $this->assertFalse($filter->isValid());
        $messages = $filter->getMessages();
        foreach ($invalidData as $key => $value) {
            $this->assertArrayHasKey($key, $messages);
            $currentMessages = $messages[$key];
            switch ($key) {
                case 'foo':
                    $this->assertArrayHasKey(Validator\StringLength::TOO_LONG, $currentMessages);
                    break;
                case 'bar':
                    $this->assertArrayHasKey(Validator\Digits::NOT_DIGITS, $currentMessages);
                    break;
                case 'baz':
                    $this->assertArrayHasKey(Validator\NotEmpty::IS_EMPTY, $currentMessages);
                    break;
                case 'nest':
                    foreach ($value as $k => $v) {
                        $this->assertArrayHasKey($k, $messages[$key]);
                        $currentMessages = $messages[$key][$k];
                        switch ($k) {
                            case 'foo':
                                $this->assertArrayHasKey(Validator\StringLength::TOO_LONG, $currentMessages);
                                break;
                            case 'bar':
                                $this->assertArrayHasKey(Validator\Digits::NOT_DIGITS, $currentMessages);
                                break;
                            case 'baz':
                                $this->assertArrayHasKey(Validator\NotEmpty::IS_EMPTY, $currentMessages);
                                break;
                            default:
                                $this->fail(sprintf('Invalid key "%s" encountered in messages array', $k));
                        }
                    }
                    break;
                default:
                    $this->fail(sprintf('Invalid key "%s" encountered in messages array', $k));
            }
        }
    }

    /**
     * Idea for this one is that one input may only need to be validated if another input is present.
     *
     * Commenting out for now, as validation context may make this irrelevant, and unsure what API to expose.
    public function testCanConditionallyInvokeValidators()
    {
        $this->markTestIncomplete();
    }
     */

    /**
     * Idea for this one is that validation may need to rely on context -- e.g., a "password confirmation"
     * field may need to know what the original password entered was in order to compare.
     */
    public function testValidationCanUseContext()
    {
        $filter = new InputFilter();

        $store = new stdClass;
        $foo   = new Input();
        $foo->getValidatorChain()->attach(new Validator\Callback(function ($value, $context) use ($store) {
            $store->value   = $value;
            $store->context = $context;
            return true;
        }));

        $bar = new Input();
        $bar->getValidatorChain()->attach(new Validator\Digits());

        $filter->add($foo, 'foo')
               ->add($bar, 'bar');

        $data = array('foo' => 'foo', 'bar' => 123);
        $filter->setData($data);

        $this->assertTrue($filter->isValid());
        $this->assertEquals('foo', $store->value);
        $this->assertEquals($data, $store->context);
    }

    /**
     * Idea here is that you can indicate that if validation for a particular input fails, we should not
     * attempt any further validation of any other inputs.
     */
    public function testInputBreakOnFailureFlagIsHonoredWhenValidating()
    {
        $filter = new InputFilter();

        $store = new stdClass;
        $foo   = new Input();
        $foo->getValidatorChain()->attach(new Validator\Callback(function ($value, $context) use ($store) {
            $store->value   = $value;
            $store->context = $context;
            return true;
        }));

        $bar = new Input();
        $bar->getValidatorChain()->attach(new Validator\Digits());
        $bar->setBreakOnFailure(true);

        $filter->add($bar, 'bar')  // adding bar first, as we want it to validate first and break the chain
               ->add($foo, 'foo');

        $data = array('bar' => 'bar', 'foo' => 'foo');
        $filter->setData($data);

        $this->assertFalse($filter->isValid());
        $this->assertObjectNotHasAttribute('value', $store);
        $this->assertObjectNotHasAttribute('context', $store);
    }

    public function testValidationSkipsFieldsMarkedNotRequiredWhenNoDataPresent()
    {
        $filter = new InputFilter();

        $foo   = new Input();
        $foo->getValidatorChain()->attach(new Validator\StringLength(3, 5));
        $foo->setRequired(false);

        $bar = new Input();
        $bar->getValidatorChain()->attach(new Validator\Digits());
        $bar->setRequired(true);

        $filter->add($foo, 'foo')
               ->add($bar, 'bar');

        $data = array('bar' => 124);
        $filter->setData($data);

        $this->assertTrue($filter->isValid());
    }

    public function testValidationSkipsFileInputsMarkedNotRequiredWhenNoFileDataIsPresent()
    {
        $filter = new InputFilter();

        $foo   = new FileInput();
        $foo->getValidatorChain()->attach(new Validator\File\UploadFile());
        $foo->setRequired(false);

        $filter->add($foo, 'foo');

        $data = array(
            'foo' => array(
                'tmp_name' => '/tmp/barfile',
                'name'     => 'barfile',
                'type'     => 'text',
                'size'     => 0,
                'error'    => 4,  // UPLOAD_ERR_NO_FILE
            )
        );
        $filter->setData($data);
        $this->assertTrue($filter->isValid());

        // Negative test
        $foo->setRequired(true);
        $filter->setData($data);
        $this->assertFalse($filter->isValid());
    }

    public function testValidationSkipsFileInputsMarkedNotRequiredWhenNoMultiFileDataIsPresent()
    {
        $filter = new InputFilter();
        $foo    = new FileInput();
        $foo->setRequired(false);
        $filter->add($foo, 'foo');

        $data = array(
            'foo' => array(array(
                'tmp_name' => '/tmp/barfile',
                'name'     => 'barfile',
                'type'     => 'text',
                'size'     => 0,
                'error'    => 4,  // UPLOAD_ERR_NO_FILE
            )),
        );
        $filter->setData($data);
        $this->assertTrue($filter->isValid());

        // Negative test
        $foo->setRequired(true);
        $filter->setData($data);
        $this->assertFalse($filter->isValid());
    }

    public function testValidationAllowsEmptyValuesToRequiredInputWhenAllowEmptyFlagIsTrue()
    {
        $filter = new InputFilter();

        $foo   = new Input('foo');
        $foo->getValidatorChain()->attach(new Validator\StringLength(3, 5));
        $foo->setRequired(true);
        $foo->setAllowEmpty(true);

        $bar = new Input();
        $bar->getValidatorChain()->attach(new Validator\Digits());
        $bar->setRequired(true);

        $filter->add($foo, '')
               ->add($bar, 'bar');

        $data = array(
            'bar' => 124,
            'foo' => '',
        );

        $filter->setData($data);

        $this->assertTrue($filter->isValid());
        $this->assertEquals('', $filter->getValue('foo'));
    }

    public function testValidationMarksInputInvalidWhenRequiredAndAllowEmptyFlagIsFalse()
    {
        $filter = new InputFilter();

        $foo   = new Input();
        $foo->getValidatorChain()->attach(new Validator\StringLength(3, 5));
        $foo->setRequired(true);
        $foo->setAllowEmpty(false);

        $bar = new Input();
        $bar->getValidatorChain()->attach(new Validator\Digits());
        $bar->setRequired(true);

        $filter->add($foo, '')
               ->add($bar, 'bar');

        $data = array('bar' => 124);
        $filter->setData($data);

        $this->assertFalse($filter->isValid());
    }

    public function testValidationMarksInputInvalidWhenNotRequiredAndAllowEmptyFlagIsFalse()
    {
        $filter = new InputFilter();

        $foo   = new Input();
        $foo->setRequired(false);
        $foo->setAllowEmpty(false);

        $filter->add($foo, 'foo');

        $data = array('foo' => '');
        $filter->setData($data);

        $this->assertFalse($filter->isValid());
    }

    public static function contextDataProvider()
    {
        return array(
            array('', 'y', true),
            array('', 'n', false),
        );
    }

    /**
     * Idea here is that an empty field may or may not be valid based on
     * context.
     */
    /**
     * @dataProvider contextDataProvider()
     */
    public function testValidationMarksInputValidWhenAllowEmptyFlagIsTrueAndContinueIfEmptyIsTrueAndContextValidatesEmptyField($allowEmpty, $blankIsValid, $valid)
    {
        $filter = new InputFilter();

        $data = array (
            'allowEmpty' => $allowEmpty,
            'blankIsValid' => $blankIsValid,
        );

        $allowEmpty = new Input();
        $allowEmpty->setAllowEmpty(true)
                   ->setContinueIfEmpty(true);

        $blankIsValid = new Input();
        $blankIsValid->getValidatorChain()->attach(new Validator\Callback(function($value, $context) {
            return ('y' === $value && empty($context['allowEmpty']));
        }));

        $filter->add($allowEmpty, 'allowEmpty')
               ->add($blankIsValid, 'blankIsValid');
        $filter->setData($data);

        $this->assertSame($valid, $filter->isValid());
    }

    public function testCanRetrieveRawValuesIndividuallyWithoutValidating()
    {
        $filter = $this->getInputFilter();
        $data = array(
            'foo' => ' bazbat ',
            'bar' => '12345',
            'nest' => array(
                'foo' => ' bazbat ',
                'bar' => '12345',
            ),
        );
        $filter->setData($data);
        $test = $filter->getRawValue('foo');
        $this->assertSame($data['foo'], $test);
    }

    public function testCanRetrieveUnvalidatedButFilteredInputValue()
    {
        $filter = $this->getInputFilter();
        $data = array(
            'foo' => ' baz 2 bat ',
            'bar' => '12345',
            'nest' => array(
                'foo' => ' bazbat ',
                'bar' => '12345',
            ),
        );
        $filter->setData($data);
        $test = $filter->getValue('foo');
        $this->assertSame('bazbat', $test);
    }

    public function testGetRequiredNotEmptyValidationMessages()
    {
        $filter = new InputFilter();

        $foo   = new Input();
        $foo->setRequired(true);
        $foo->setAllowEmpty(false);

        $filter->add($foo, 'foo');

        $data = array('foo' => null);
        $filter->setData($data);

        $this->assertFalse($filter->isValid());
        $messages = $filter->getMessages();
        $this->assertArrayHasKey('foo', $messages);
        $this->assertNotEmpty($messages['foo']);
    }
    public function testHasUnknown()
    {
        $filter = $this->getInputFilter();
        $validData = array(
            'foo' => ' bazbat ',
            'bar' => '12345',
            'baz' => ''
        );
        $filter->setData($validData);
        $this->assertFalse($filter->hasUnknown());

        $filter = $this->getInputFilter();
        $invalidData = array(
            'bar' => '12345',
            'baz' => '',
            'gru' => '',
        );
        $filter->setData($invalidData);
        $this->assertTrue($filter->hasUnknown());
    }
    public function testGetUknown()
    {
        $filter = $this->getInputFilter();
        $unknown = array(
            'bar' => '12345',
            'baz' => '',
            'gru' => 10,
            'test' => 'ok',
        );
        $filter->setData($unknown);
        $unknown = $filter->getUnknown();
        $this->assertEquals(2, count($unknown));
        $this->assertTrue(array_key_exists('gru', $unknown));
        $this->assertEquals(10, $unknown['gru']);
        $this->assertTrue(array_key_exists('test', $unknown));
        $this->assertEquals('ok', $unknown['test']);

        $filter = $this->getInputFilter();
        $validData = array(
            'foo' => ' bazbat ',
            'bar' => '12345',
            'baz' => ''
        );
        $filter->setData($validData);
        $unknown = $filter->getUnknown();
        $this->assertEquals(0, count($unknown));
    }

    public function testValidateUseExplodeAndInstanceOf()
    {
        $filter = new InputFilter();

        $input = new Input();
        $input->setRequired(true);

        $input->getValidatorChain()->attach(
            new \Zend\Validator\Explode(
                array(
                    'validator' => new \Zend\Validator\IsInstanceOf(
                        array(
                            'className' => 'Zend\InputFilter\Input'
                        )
                    )
                )
            )
        );

        $filter->add($input, 'example');

        $data = array(
            'example' => array(
                $input
            )
        );

        $filter->setData($data);
        $this->assertTrue($filter->isValid());
    }

    public function testGetInputs()
    {
        $filter = new InputFilter();

        $foo = new Input('foo');
        $bar = new Input('bar');

        $filter->add($foo);
        $filter->add($bar);

        $filters = $filter->getInputs();

        $this->assertCount(2, $filters);
        $this->assertEquals('foo', $filters['foo']->getName());
        $this->assertEquals('bar', $filters['bar']->getName());
    }

    /**
     * @group 4996
     */
    public function testAddingExistingInputWillMergeIntoExisting()
    {
        $filter = new InputFilter();

        $foo1    = new Input('foo');
        $foo1->setRequired(true);
        $filter->add($foo1);

        $foo2    = new Input('foo');
        $foo2->setRequired(false);
        $filter->add($foo2);

        $this->assertFalse($filter->get('foo')->isRequired());
    }
}
