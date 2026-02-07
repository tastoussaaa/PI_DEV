<?php

namespace App\Form;

use App\Entity\Consultation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class ConsultationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateConsultation', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Consultation date',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input']
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'Reason for consultation']
            ])
            ->add('name', TextType::class, [
                'label' => 'First name',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'Given name']
            ])
            ->add('familyName', TextType::class, [
                'label' => 'Family name',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'Surname']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'placeholder' => 'patient@example.com']
            ])
            ->add('sex', ChoiceType::class, [
                'label' => 'Sex',
                'choices' => [
                    'Male' => 'male',
                    'Female' => 'female',
                ],
                'placeholder' => 'Choose',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__select']
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Age',
                'row_attr' => ['class' => 'form__field'],
                'attr' => ['class' => 'form__input', 'min' => 0]
            ]);
            
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Consultation::class,
        ]);
    }
}