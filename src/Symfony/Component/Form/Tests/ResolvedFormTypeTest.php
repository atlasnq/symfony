<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\Test\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedFormTypeTest extends TestCase
{
    /**
     * @var MockObject&FormTypeInterface
     */
    private $parentType;

    /**
     * @var MockObject&FormTypeInterface
     */
    private $type;

    /**
     * @var MockObject&FormTypeExtensionInterface
     */
    private $extension1;

    /**
     * @var MockObject&FormTypeExtensionInterface
     */
    private $extension2;

    /**
     * @var ResolvedFormType
     */
    private $parentResolvedType;

    /**
     * @var ResolvedFormType
     */
    private $resolvedType;

    protected function setUp(): void
    {
        $this->parentType = $this->getMockFormType();
        $this->type = $this->getMockFormType();
        $this->extension1 = $this->getMockFormTypeExtension();
        $this->extension2 = $this->getMockFormTypeExtension();
        $this->parentResolvedType = new ResolvedFormType($this->parentType);
        $this->resolvedType = new ResolvedFormType($this->type, [$this->extension1, $this->extension2], $this->parentResolvedType);
    }

    public function testGetOptionsResolver()
    {
        $i = 0;

        $assertIndexAndAddOption = function ($index, $option, $default) use (&$i) {
            return function (OptionsResolver $resolver) use (&$i, $index, $option, $default) {
                $this->assertEquals($index, $i, 'Executed at index '.$index);

                ++$i;

                $resolver->setDefaults([$option => $default]);
            };
        };

        // First the default options are generated for the super type
        $this->parentType->expects($this->once())
            ->method('configureOptions')
            ->willReturnCallback($assertIndexAndAddOption(0, 'a', 'a_default'));

        // The form type itself
        $this->type->expects($this->once())
            ->method('configureOptions')
            ->willReturnCallback($assertIndexAndAddOption(1, 'b', 'b_default'));

        // And its extensions
        $this->extension1->expects($this->once())
            ->method('configureOptions')
            ->willReturnCallback($assertIndexAndAddOption(2, 'c', 'c_default'));

        $this->extension2->expects($this->once())
            ->method('configureOptions')
            ->willReturnCallback($assertIndexAndAddOption(3, 'd', 'd_default'));

        $givenOptions = ['a' => 'a_custom', 'c' => 'c_custom'];
        $resolvedOptions = ['a' => 'a_custom', 'b' => 'b_default', 'c' => 'c_custom', 'd' => 'd_default'];

        $resolver = $this->resolvedType->getOptionsResolver();

        $this->assertEquals($resolvedOptions, $resolver->resolve($givenOptions));
    }

    public function testCreateBuilder()
    {
        $givenOptions = ['a' => 'a_custom', 'c' => 'c_custom'];
        $resolvedOptions = ['a' => 'a_custom', 'b' => 'b_default', 'c' => 'c_custom', 'd' => 'd_default'];
        $optionsResolver = $this->createMock(OptionsResolver::class);

        $this->resolvedType = $this->getMockBuilder(ResolvedFormType::class)
            ->setConstructorArgs([$this->type, [$this->extension1, $this->extension2], $this->parentResolvedType])
            ->setMethods(['getOptionsResolver'])
            ->getMock();

        $this->resolvedType->expects($this->once())
            ->method('getOptionsResolver')
            ->willReturn($optionsResolver);

        $optionsResolver->expects($this->once())
            ->method('resolve')
            ->with($givenOptions)
            ->willReturn($resolvedOptions);

        $factory = $this->getMockFormFactory();
        $builder = $this->resolvedType->createBuilder($factory, 'name', $givenOptions);

        $this->assertSame($this->resolvedType, $builder->getType());
        $this->assertSame($resolvedOptions, $builder->getOptions());
        $this->assertNull($builder->getDataClass());
    }

    public function testCreateBuilderWithDataClassOption()
    {
        $givenOptions = ['data_class' => 'Foo'];
        $resolvedOptions = ['data_class' => \stdClass::class];
        $optionsResolver = $this->createMock(OptionsResolver::class);

        $this->resolvedType = $this->getMockBuilder(ResolvedFormType::class)
            ->setConstructorArgs([$this->type, [$this->extension1, $this->extension2], $this->parentResolvedType])
            ->setMethods(['getOptionsResolver'])
            ->getMock();

        $this->resolvedType->expects($this->once())
            ->method('getOptionsResolver')
            ->willReturn($optionsResolver);

        $optionsResolver->expects($this->once())
            ->method('resolve')
            ->with($givenOptions)
            ->willReturn($resolvedOptions);

        $factory = $this->getMockFormFactory();
        $builder = $this->resolvedType->createBuilder($factory, 'name', $givenOptions);

        $this->assertSame($this->resolvedType, $builder->getType());
        $this->assertSame($resolvedOptions, $builder->getOptions());
        $this->assertSame(\stdClass::class, $builder->getDataClass());
    }

    public function testFailsCreateBuilderOnInvalidFormOptionsResolution()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('An error has occurred resolving the options of the form "Symfony\Component\Form\Extension\Core\Type\HiddenType": The required option "foo" is missing.');
        $optionsResolver = (new OptionsResolver())
            ->setRequired('foo')
        ;
        $this->resolvedType = $this->getMockBuilder(ResolvedFormType::class)
            ->setConstructorArgs([$this->type, [$this->extension1, $this->extension2], $this->parentResolvedType])
            ->setMethods(['getOptionsResolver', 'getInnerType'])
            ->getMock()
        ;
        $this->resolvedType->expects($this->once())
            ->method('getOptionsResolver')
            ->willReturn($optionsResolver)
        ;
        $this->resolvedType->expects($this->once())
            ->method('getInnerType')
            ->willReturn(new HiddenType())
        ;
        $factory = $this->getMockFormFactory();

        $this->resolvedType->createBuilder($factory, 'name');
    }

    public function testBuildForm()
    {
        $i = 0;

        $assertIndex = function ($index) use (&$i) {
            return function () use (&$i, $index) {
                $this->assertEquals($index, $i, 'Executed at index '.$index);

                ++$i;
            };
        };

        $options = ['a' => 'Foo', 'b' => 'Bar'];
        $builder = $this->createMock(FormBuilderInterface::class);

        // First the form is built for the super type
        $this->parentType->expects($this->once())
            ->method('buildForm')
            ->with($builder, $options)
            ->willReturnCallback($assertIndex(0));

        // Then the type itself
        $this->type->expects($this->once())
            ->method('buildForm')
            ->with($builder, $options)
            ->willReturnCallback($assertIndex(1));

        // Then its extensions
        $this->extension1->expects($this->once())
            ->method('buildForm')
            ->with($builder, $options)
            ->willReturnCallback($assertIndex(2));

        $this->extension2->expects($this->once())
            ->method('buildForm')
            ->with($builder, $options)
            ->willReturnCallback($assertIndex(3));

        $this->resolvedType->buildForm($builder, $options);
    }

    public function testCreateView()
    {
        $form = $this->bootstrapForm();

        $view = $this->resolvedType->createView($form);

        $this->assertInstanceOf(FormView::class, $view);
        $this->assertNull($view->parent);
    }

    public function testCreateViewWithParent()
    {
        $form = $this->bootstrapForm();
        $parentView = $this->createMock(FormView::class);

        $view = $this->resolvedType->createView($form, $parentView);

        $this->assertInstanceOf(FormView::class, $view);
        $this->assertSame($parentView, $view->parent);
    }

    public function testBuildView()
    {
        $options = ['a' => '1', 'b' => '2'];
        $form = $this->bootstrapForm();
        $view = $this->createMock(FormView::class);

        $i = 0;

        $assertIndex = function ($index) use (&$i) {
            return function () use (&$i, $index) {
                $this->assertEquals($index, $i, 'Executed at index '.$index);

                ++$i;
            };
        };

        // First the super type
        $this->parentType->expects($this->once())
            ->method('buildView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(0));

        // Then the type itself
        $this->type->expects($this->once())
            ->method('buildView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(1));

        // Then its extensions
        $this->extension1->expects($this->once())
            ->method('buildView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(2));

        $this->extension2->expects($this->once())
            ->method('buildView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(3));

        $this->resolvedType->buildView($view, $form, $options);
    }

    public function testFinishView()
    {
        $options = ['a' => '1', 'b' => '2'];
        $form = $this->bootstrapForm();
        $view = $this->createMock(FormView::class);

        $i = 0;

        $assertIndex = function ($index) use (&$i) {
            return function () use (&$i, $index) {
                $this->assertEquals($index, $i, 'Executed at index '.$index);

                ++$i;
            };
        };

        // First the super type
        $this->parentType->expects($this->once())
            ->method('finishView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(0));

        // Then the type itself
        $this->type->expects($this->once())
            ->method('finishView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(1));

        // Then its extensions
        $this->extension1->expects($this->once())
            ->method('finishView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(2));

        $this->extension2->expects($this->once())
            ->method('finishView')
            ->with($view, $form, $options)
            ->willReturnCallback($assertIndex(3));

        $this->resolvedType->finishView($view, $form, $options);
    }

    public function testGetBlockPrefix()
    {
        $this->type->expects($this->once())
            ->method('getBlockPrefix')
            ->willReturn('my_prefix');

        $resolvedType = new ResolvedFormType($this->type);

        $this->assertSame('my_prefix', $resolvedType->getBlockPrefix());
    }

    /**
     * @dataProvider provideTypeClassBlockPrefixTuples
     */
    public function testBlockPrefixDefaultsToFQCNIfNoName($typeClass, $blockPrefix)
    {
        $resolvedType = new ResolvedFormType(new $typeClass());

        $this->assertSame($blockPrefix, $resolvedType->getBlockPrefix());
    }

    public function provideTypeClassBlockPrefixTuples()
    {
        return [
            [Fixtures\FooType::class, 'foo'],
            [Fixtures\Foo::class, 'foo'],
            [Fixtures\Type::class, 'type'],
            [Fixtures\FooBarHTMLType::class, 'foo_bar_html'],
            [__NAMESPACE__.'\Fixtures\Foo1Bar2Type', 'foo1_bar2'],
            [Fixtures\FBooType::class, 'f_boo'],
        ];
    }

    private function getMockFormType($typeClass = AbstractType::class): MockObject&FormTypeInterface
    {
        return $this->getMockBuilder($typeClass)->setMethods(['getBlockPrefix', 'configureOptions', 'finishView', 'buildView', 'buildForm'])->getMock();
    }

    private function getMockFormTypeExtension(): MockObject&FormTypeExtensionInterface
    {
        return $this->getMockBuilder(AbstractTypeExtension::class)->setMethods(['getExtendedTypes', 'configureOptions', 'finishView', 'buildView', 'buildForm'])->getMock();
    }

    private function getMockFormFactory(): MockObject&FormFactoryInterface
    {
        return $this->createMock(FormFactoryInterface::class);
    }

    private function bootstrapForm(): Form
    {
        $config = $this->createMock(FormConfigInterface::class);
        $config->method('getInheritData')->willReturn(false);
        $config->method('getName')->willReturn('');

        return new Form($config);
    }
}
