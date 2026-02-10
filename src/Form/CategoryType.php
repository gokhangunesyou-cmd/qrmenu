<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Media;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Kategori Adı',
                'attr' => ['placeholder' => 'Örn: Ana Yemekler']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Açıklama',
                'required' => false,
                'attr' => ['rows' => 3]
            ])
            ->add('icon', TextType::class, [
                'label' => 'İkon (Opsiyonel)',
                'required' => false,
                'help' => 'Bootstrap Icon sınıfı (örn: bi-egg-fried)'
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Kategori Görseli',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Lütfen geçerli bir görsel yükleyin (JPEG, PNG, WEBP)',
                    ])
                ],
                'attr' => ['accept' => 'image/*']
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Sıralama',
                'data' => 0
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Aktif',
                'required' => false,
                'data' => true
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
