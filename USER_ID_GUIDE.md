# User ID Access Guide

This guide shows how to access the connected user's ID and related data throughout your application.

## In Controllers

### Using BaseController (Recommended)

Extend `BaseController` instead of `AbstractController` to get easy access to user helpers:

```php
<?php
namespace App\Controller;

use App\Service\UserService;
use Symfony\Component\Routing\Attribute\Route;

class MyController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/my-page', name: 'my_page')]
    public function myPage()
    {
        // Get current user ID
        $userId = $this->getCurrentUserId();
        
        // Get current user object
        $user = $this->getCurrentUser();
        
        // Get user type (medecin, patient, aidesoignant)
        $userType = $this->getCurrentUserType();
        
        // Get specific entity for current user
        $userEntity = $this->getCurrentUserEntity(); // Returns Medecin|Patient|AideSoignant|null
        
        // Get specific type entities
        $medecin = $this->getCurrentMedecin(); // null if not a medecin
        $patient = $this->getCurrentPatient(); // null if not a patient
        $aideSoignant = $this->getCurrentAideSoignant(); // null if not aide soignant
        
        // Check user type
        if ($this->isCurrentUserMedecin()) {
            // Do something for medecins
        }
        
        if ($this->isCurrentUserPatient()) {
            // Do something for patients
        }
        
        if ($this->isCurrentUserAideSoignant()) {
            // Do something for aide soignants
        }
        
        // Pass data to template
        return $this->render('my-page.html.twig', [
            'userId' => $userId,
            'user' => $user,
            'userEntity' => $userEntity,
        ]);
    }
}
```

### Using UserService in Any Controller

You can also inject `UserService` into any controller:

```php
<?php
namespace App\Controller;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AnyController extends AbstractController
{
    public function __construct(private UserService $userService) {}

    public function someAction()
    {
        $userId = $this->userService->getCurrentUserId();
        $userType = $this->userService->getCurrentUserType();
    }
}
```

## In Templates

### Global Variables Available

These variables are automatically available in all Twig templates:

```twig
{# Get current user ID #}
{{ current_user_id }}

{# Get current user object #}
{{ current_user }}

{# Get current user type #}
{{ current_user_type }}

{# Get specific user entity (Medecin/Patient/AideSoignant) #}
{{ current_user_entity }}

{# Check if user is authenticated #}
{{ is_authenticated }}

{# Check user type #}
{{ is_medecin }}
{{ is_patient }}
{{ is_aide_soignant }}
```

### Example Usage in Templates

```twig
{% if is_authenticated %}
    <div>User ID: {{ current_user_id }}</div>
    <div>Name: {{ current_user.fullName }}</div>
    <div>Email: {{ current_user.email }}</div>
    <div>Type: {{ current_user_type }}</div>
    
    {% if is_medecin %}
        <div>Specialty: {{ current_user_entity.specialite }}</div>
        <div>RPPS: {{ current_user_entity.rpps }}</div>
    {% endif %}
    
    {% if is_patient %}
        <div>Birth Date: {{ current_user_entity.birthDate|date('Y-m-d') }}</div>
        <div>SSN: {{ current_user_entity.ssn }}</div>
    {% endif %}
    
    {% if is_aide_soignant %}
        <div>ADELI: {{ current_user_entity.adeli }}</div>
    {% endif %}
{% else %}
    <p>Please log in</p>
{% endif %}
```

## Security & Access Control

### Protect Routes by Role

In `config/packages/security.yaml`, routes are automatically protected:

```yaml
access_control:
    - { path: ^/login, roles: PUBLIC_ACCESS }
    - { path: ^/register, roles: PUBLIC_ACCESS }
    - { path: ^/medecin, roles: ROLE_USER }
    - { path: ^/patient, roles: ROLE_USER }
    - { path: ^/aide_soignant, roles: ROLE_USER }
    - { path: ^/, roles: ROLE_USER }
```

### Deny Access in Controller

```php
// Ensure user is authenticated
$this->denyAccessUnlessGranted('ROLE_USER');

// Or use UserService
if (!$this->userService->isAuthenticated()) {
    throw $this->createNotFoundException('Access denied');
}

// Check specific user type
if (!$this->isCurrentUserMedecin()) {
    throw $this->createNotFoundException('Only medecins can access this');
}
```

## Querying by User ID

### In Repository Classes

```php
<?php
namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class PatientRepository extends EntityRepository
{
    public function findByUserId(int $userId): ?Patient
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

### Using in Controller

```php
$patientRepo = $this->em->getRepository(Patient::class);
$patient = $patientRepo->findByUserId($this->getCurrentUserId());
```

## Common Patterns

### Redirect After Login

The login controller already handles this:

```php
// SecurityController automatically redirects based on user type:
// - Medecin → /medecin/dashboard
// - Patient → /patient/dashboard
// - AideSoignant → /aidesoingnant/dashboard
// - Admin → /admin/dashboard
```

### Fetch User-Specific Data

```php
#[Route('/my-data', name: 'my_data')]
public function myData()
{
    // Get user's specific entity with all related data
    $entity = $this->getCurrentUserEntity();
    
    if ($entity instanceof Medecin) {
        $consultations = $entity->getConsultations();
        $formations = $entity->getFormations();
    } elseif ($entity instanceof Patient) {
        $consultations = $entity->getConsultations();
    }
    
    return $this->render('my-data.html.twig', [
        'data' => $entity,
    ]);
}
```

### Multi-User Support in Templates

```twig
{% if is_authenticated %}
    {% if is_medecin %}
        {% include 'medecin/navbar.html.twig' %}
    {% elseif is_patient %}
        {% include 'patient/navbar.html.twig' %}
    {% elseif is_aide_soignant %}
        {% include 'aide_soignant/navbar.html.twig' %}
    {% endif %}
{% endif %}
```

## Logout

The logout route is automatically handled:

```twig
<a href="{{ path('app_logout') }}">Logout</a>
```

This will clear the session and redirect to the login page.
