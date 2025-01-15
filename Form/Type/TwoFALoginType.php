<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class TwoFALoginType extends AbstractType
{
    /**
     * Builds the form type.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'code',
            TextType::class,
            [
                'label'      => 'mautic.2fa.code',
                'label_attr' => ['class' => 'control-label'],
                'required'   => true,
                'attr'       => [
                    'class' => 'form-control',
                ],
            ]
        );
    }
}
