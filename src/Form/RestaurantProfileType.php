<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Restaurant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

class RestaurantProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $themeColors = $options['theme_colors'];
        $localeChoices = $this->buildLocaleChoices($options['available_locales']);

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
                'constraints' => [new Url(requireTld: false, message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('facebookUrl', TextType::class, [
                'label' => 'Facebook URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['facebook'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://facebook.com/...'],
                'constraints' => [new Url(requireTld: false, message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('twitterUrl', TextType::class, [
                'label' => 'Twitter/X URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['twitter'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://x.com/...'],
                'constraints' => [new Url(requireTld: false, message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('tiktokUrl', TextType::class, [
                'label' => 'TikTok URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['tiktok'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://tiktok.com/@...'],
                'constraints' => [new Url(requireTld: false, message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('websiteUrl', TextType::class, [
                'label' => 'Website URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['website'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://...'],
                'constraints' => [new Url(requireTld: false, message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('googleMapsUrl', TextType::class, [
                'label' => 'Google Harita URL',
                'mapped' => false,
                'required' => false,
                'data' => (string) ($options['social_links']['google_maps'] ?? ''),
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://maps.google.com/...'],
                'constraints' => [new Url(requireTld: false, message: 'Geçerli bir URL giriniz.')],
            ])
            ->add('menuTemplate', ChoiceType::class, [
                'label' => 'Menü Şablonu',
                'choices' => [
                    'Showcase Spotlight' => 'showcase',
                    'Editorial Slate' => 'editorial',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('countProductDetailViews', CheckboxType::class, [
                'label' => 'Urun Detay Girislerini Say',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('whatsappOrderEnabled', CheckboxType::class, [
                'label' => 'WhatsApp ile Siparis Al',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('enabledLocales', ChoiceType::class, [
                'label' => 'Yayın Dilleri',
                'choices' => $localeChoices,
                'multiple' => true,
                'expanded' => true,
                'constraints' => [
                    new Count(min: 1, minMessage: 'En az bir dil seçiniz.'),
                ],
            ])
            ->add('themePrimaryColor', ColorType::class, [
                'label' => 'Ana Renk',
                'mapped' => false,
                'data' => (string) ($themeColors['primary'] ?? '#9A3412'),
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('themeSecondaryColor', ColorType::class, [
                'label' => 'İkincil Renk',
                'mapped' => false,
                'data' => (string) ($themeColors['secondary'] ?? '#0F766E'),
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('themeBackgroundColor', ColorType::class, [
                'label' => 'Arka Plan',
                'mapped' => false,
                'data' => (string) ($themeColors['background'] ?? '#FFF8F1'),
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('themeSurfaceColor', ColorType::class, [
                'label' => 'Kart Rengi',
                'mapped' => false,
                'data' => (string) ($themeColors['surface'] ?? '#FFFFFF'),
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('themeTextColor', ColorType::class, [
                'label' => 'Metin Rengi',
                'mapped' => false,
                'data' => (string) ($themeColors['text'] ?? '#1F2937'),
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('themeAccentColor', ColorType::class, [
                'label' => 'Vurgu Rengi',
                'mapped' => false,
                'data' => (string) ($themeColors['accent'] ?? '#F59E0B'),
                'attr' => ['class' => 'form-control form-control-color'],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new File(maxSize: '10M'),
                ],
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'Kapak Fotoğrafı',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new File(maxSize: '10M'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Restaurant::class,
            'social_links' => [],
            'theme_colors' => [],
            'available_locales' => [],
        ]);
        $resolver->setAllowedTypes('available_locales', 'array');
    }

    /**
     * @param array<string, string> $availableLocales
     *
     * @return array<string, string>
     */
    private function buildLocaleChoices(array $availableLocales): array
    {
        $choices = [];
        foreach ($availableLocales as $code => $label) {
            $localeCode = strtolower(trim((string) $code));
            if ($localeCode === '' || $label === '') {
                continue;
            }

            $choices[(string) $label] = $localeCode;
        }

        return $choices;
    }
}
