# Système de Validation Côté Serveur avec Symfony

## 📋 Vue d'ensemble

Ce guide explique comment implémenter une validation complète côté serveur avec Symfony, sans utiliser JavaScript ni les attributs de validation HTML5.

## ✅ Caractéristiques

- ✅ **Validation côté serveur uniquement** - Aucun JavaScript
- ✅ **Sécurité garantie** - Validation impossible à contourner
- ✅ **Messages d'erreur personnalisés** - En français
- ✅ **Validateurs personnalisés** - Pour les règles métier complexes
- ✅ **Templates réutilisables** - Pour l'affichage des erreurs
- ✅ **Gestion CSRF** - Protégée automatiquement

## 🛠️ Composants créés

### 1. Validateurs Personnalisés

#### `ValidAddress.php` & `ValidAddressValidator.php`
```php
// Utilisation dans l'entité
#[ValidAddress]
private ?string $adresse = null;
```

Valide:
- Longueur minimale (10 caractères)
- Format: numéro rue requis
- Interdiction de caractères spéciaux dangereux

### 2. Entités avec Validations Complètes

**Fichiers modifiés:**
- `src/Entity/Produit.php` - Validations pour nom, prix, stock, catégorie
- `src/Entity/Commande.php` - Validations pour numéro, statut, adresse, total
- `src/Entity/User.php` - Validations pour email, téléphone, nom, prénom

**Contraintes utilisées:**
```php
#[Assert\NotBlank(message: "...")]
#[Assert\Email(message: "...")]
#[Assert\Length(min: X, max: Y)]
#[Assert\Type(type: 'numeric')]
#[Assert\Choice(choices: [...])]
#[Assert\Regex(pattern: "/.../")]
#[Assert\GreaterThan(value: X)]
```

### 3. FormTypes Avancés

#### `AdvancedProduitFormType.php`
```php
// Crée un formulaire avec validations au niveau du formulaire
$builder
    ->add('nom', TextType::class, [
        'label' => 'Nom du produit',
        'constraints' => [
            new Assert\NotBlank(),
            new Assert\Length(['min' => 3, 'max' => 200]),
        ]
    ])
```

#### `AdvancedCommandeFormType.php`
```php
// Formulaire pour créer/éditer une commande
```

#### `AdvancedUserFormType.php`
```php
// Formulaire pour créer/éditer un utilisateur
```

### 4. Templates Twig Réutilisables

#### `templates/partials/form_errors.html.twig`
Affiche tous les erreurs de validation du formulaire

```twig
{% include 'partials/form_errors.html.twig' %}
```

#### `templates/partials/form_field.html.twig`
Rend un champ avec gestion automatique des erreurs

```twig
{% include 'partials/form_field.html.twig' with {'field': form.nom} %}
```

## 🚀 Utilisation

### 1. Dans un Contrôleur

```php
use App\Form\AdvancedProduitFormType;

class ProduitController extends AbstractController
{
    #[Route('/produit/new', name: 'produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $produit = new Produit();
        $form = $this->createForm(AdvancedProduitFormType::class, $produit);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ Les données sont validées côté serveur
            $em->persist($produit);
            $em->flush();
            return $this->redirectToRoute('produit_show', ['id' => $produit->getId()]);
        }
        
        return $this->render('produit/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
```

### 2. Dans un Template

```twig
{% extends 'base.html.twig' %}

{% block content %}
<div class="container">
    <h1>Créer un produit</h1>
    
    {# Afficher les erreurs globales #}
    {% include 'partials/form_errors.html.twig' %}
    
    <form method="post">
        {# Protection CSRF #}
        {{ form_row(form._token) }}
        
        {# Champs avec gestion d'erreurs automatique #}
        {% include 'partials/form_field.html.twig' with {'field': form.nom} %}
        {% include 'partials/form_field.html.twig' with {'field': form.description} %}
        {% include 'partials/form_field.html.twig' with {'field': form.prix} %}
        
        <button type="submit" class="btn btn-primary">Créer</button>
    </form>
</div>
{% endblock %}
```

## 🔍 Flux de Validation

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Utilisateur soumet le formulaire via POST                    │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. $form->handleRequest($request)                               │
│    Les données sont liées au formulaire                         │
└────────────────────────────┬────────────────────────────────────┘
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. $form->isValid() vérifie:                                    │
│    - Validateurs de l'entité (@Assert\...)                      │
│    - Validateurs du formulaire (FormType)                       │
│    - Validateurs personnalisés (@ValidAddress)                  │
└────────────────────────────┬────────────────────────────────────┘
                             ▴
                    Erreurs détectées?
                     │          │
                     OUI        NON
                     │          │
                     ▼          ▼
            ┌──────────────┐  ┌──────────────────────────┐
            │ Afficher le  │  │ Traiter les données      │
            │ formulaire   │  │ Persister en BD          │
            │ + erreurs    │  │ Rediriger                │
            └──────────────┘  └──────────────────────────┘
```

## 📝 Contraintes de Validation Disponibles

### Types de base
- `NotBlank` - Champ obligatoire
- `NotNull` - Valeur not null
- `Type` - Type spécifique (numeric, string, etc.)
- `Length` - Longueur min/max
- `Email` - Format email
- `Regex` - Correspond à un pattern regex
- `Choice` - Valeur parmi des choix

### Nombres
- `GreaterThan` - Strictement supérieur
- `GreaterThanOrEqual` - Supérieur ou égal
- `LessThan` - Strictement inférieur
- `LessThanOrEqual` - Inférieur ou égal
- `Positive` - Nombre positif
- `PositiveOrZero` - Nombre positif ou zéro

### Collections
- `Count` - Nombre d'éléments
- `Valid` - Valider les éléments imbriqués
- `Unique` - Éléments uniques

### Doctrine
- `UniqueEntity` - Valeur unique en BD

## 🎯 Bonnes pratiques

### ✅ À faire
1. **Valider côté serveur** - C'est obligatoire
2. **Utiliser des FormTypes** - Pour la cohérence
3. **Personnaliser les messages** - En français
4. **Revalider en contrôleur** - Même après validation en entité
5. **Logger les erreurs** - Pour le débogage

### ❌ À ne pas faire
1. **Faire confiance à la validation JavaScript** - Facilement contournable
2. **Ignorer les erreurs du formulaire** - Les afficher à l'utilisateur
3. **Mélanger validations HTML5 et PHP** - Utiliser PHP uniquement
4. **Oublier la validation CSRF** - Utiliser `{{ form_row(form._token) }}`

## 🧪 Tests

### Tester la validation
```php
use Symfony\Component\Validator\ValidatorInterface;

class ProduitValidationTest extends TestCase
{
    public function testProduitNomRequired()
    {
        $produit = new Produit();
        $produit->setNom(''); // Nom vide
        
        $errors = $this->validator->validate($produit);
        $this->assertCount(1, $errors);
        $this->assertEquals('Le nom est obligatoire', $errors[0]->getMessage());
    }
}
```

## 📚 Ressources

- [Symfony Validator](https://symfony.com/doc/current/validation.html)
- [Constraints Reference](https://symfony.com/doc/current/reference/constraints.html)
- [Form Types Reference](https://symfony.com/doc/current/reference/forms/types.html)

## 🔧 Configuration

Aucune configuration supplémentaire n'est requise. Symfony utilise la validation automatiquement:

1. Annotations dans l'entité → validées automatiquement
2. FormType → valide lors de handleRequest()
3. Template → affiche les erreurs

## 💡 Exemplesi de Messages d'Erreur

```
Le nom du produit est obligatoire.
Le prix doit être un nombre valide.
Le prix doit être supérieur à 0 €.
L'adresse doit contenir au moins 10 caractères.
Le numéro de commande doit contenir uniquement des lettres majuscules, des chiffres et des tirets.
```

## 📞 Support

Pour intégrer ce système dans votre projet:

1. Copier les validateurs personnalisés
2. Améliorer les entités avec @Assert
3. Créer/adapter les FormTypes
4. Utiliser les templates Twig
5. Adapter les contrôleurs
6. Tester chaque formulaire
