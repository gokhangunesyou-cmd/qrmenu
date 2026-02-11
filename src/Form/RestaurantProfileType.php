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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

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
            ->add('countryCode', TextType::class, [
                'label' => 'Ülke Kodu',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 2, 'placeholder' => 'TR'],
                'constraints' => [
                    new Length(max: 2),
                    new Regex(pattern: '/^[A-Za-z]{2}$/', message: 'Ülke kodu iki harf olmalıdır.'),
                ],
            ])
            ->add('latitude', TextType::class, [
                'label' => 'Enlem (Latitude)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '41.0082'],
                'constraints' => [
                    new Regex(pattern: '/^-?\d{1,3}(\.\d+)?$/', message: 'Geçerli bir enlem giriniz.'),
                ],
            ])
            ->add('longitude', TextType::class, [
                'label' => 'Boylam (Longitude)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '28.9784'],
                'constraints' => [
                    new Regex(pattern: '/^-?\d{1,3}(\.\d+)?$/', message: 'Geçerli bir boylam giriniz.'),
                ],
            ])
            ->add('instagramUrl', TextType::class, [
                'label' => 'Instagram URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['instagram'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://instagram.com/...'],
                'constraints' => [new Url(message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('facebookUrl', TextType::class, [
                'label' => 'Facebook URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['facebook'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://facebook.com/...'],
                'constraints' => [new Url(message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('twitterUrl', TextType::class, [
                'label' => 'Twitter/X URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['twitter'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://x.com/...'],
                'constraints' => [new Url(message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('tiktokUrl', TextType::class, [
                'label' => 'TikTok URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['tiktok'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://tiktok.com/@...'],
                'constraints' => [new Url(message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('websiteUrl', TextType::class, [
                'label' => 'Website URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['website'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://...'],
                'constraints' => [new Url(message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('googleMapsUrl', TextType::class, [
                'label' => 'Google Harita URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['google_maps'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://maps.google.com/...'],
                'constraints' => [new Url(message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
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
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Restaurant::class,
            'social_links' => [],
        ]);
    }
}
