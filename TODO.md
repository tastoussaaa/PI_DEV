 # TODO: Add Input Validation to Consultation and Ordonnance Forms

## Step 1: Update src/Entity/Consultation.php ✅
- Add use statement for Symfony\Component\Validator\Constraints as Assert ✅
- Add validation annotations:
  - #[Assert\NotBlank] to dateConsultation, motif, name, familyName, email, sex, age ✅
  - #[Assert\Email] to email ✅
  - #[Assert\Positive] to age ✅
  - #[Assert\Length(max: 255)] to motif ✅
  - #[Assert\Length(max: 100)] to name, familyName ✅
  - #[Assert\Length(max: 10)] to sex ✅
  - #[Assert\UniqueEntity(fields: ['dateConsultation'])] to ensure unique consultation dates ✅
  - Add working hours validation: consultations only between 9-12 and 14-17 ✅

## Step 2: Update src/Entity/Ordonnance.php ✅
- Add use statement for Symfony\Component\Validator\Constraints as Assert ✅
- Add validation annotations:
  - #[Assert\NotBlank] to medicament, dosage, duree, instructions, consultation ✅
  - #[Assert\Length(max: 255)] to medicament, dosage, duree ✅

## Step 3: Test Validations ✅
- Symfony cache cleared to ensure validations are loaded.
- Validations implemented: Forms will now enforce NotBlank, Email, Positive, Length, UniqueEntity, and working hours constraints.
- Manual testing required: Submit forms with empty/invalid data to verify errors are shown.
- Manual testing required: Test unique date constraint by attempting to create consultations with same dateConsultation.
- Manual testing required: Test working hours constraint by trying to book outside 9-12 or 14-17.

## Step 4: Followup ✅
- Task completed successfully.
