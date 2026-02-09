<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Restaurant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class RestaurantProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Restoran Adı',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Açıklama',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Telefon',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-posta',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adres',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('city', TextType::class, [
                'label' => 'Şehir',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Lütfen geçerli bir resim dosyası yükleyin (JPEG, PNG, WebP)',
                    ]),
                ],
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'Kapak Fotoğrafı',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Lütfen geçerli bir resim dosyası yükleyin (JPEG, PNG, WebP)',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Restaurant::class,
        ]);
    }
}
