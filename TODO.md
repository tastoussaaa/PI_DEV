# TODO: Implement "Mes Missions" for Aide-Soignants

- [x] Update src/Entity/Mission.php: Add ManyToOne relation to AideSoignant
- [x] Update src/Entity/AideSoignant.php: Add OneToMany relation to Mission
- [x] Generate Doctrine migration for new relation
- [x] Update templates/side_bar.html.twig: Add "Mes Missions" link for aide-soignants
- [x] Add route in config/routes.yaml for /aidesoingnant/missions
- [x] Update src/Controller/AideSoingnantController.php: Add missions() method to list filtered DemandeAide
- [x] Create templates/aide_soingnant/missions.html.twig: Template with list and accept/refuse buttons
- [x] Add acceptMission() and refuseMission() methods in controller
- [x] Run Doctrine migration
- [x] Fix bugs: server errors, price validation, button functionality
- [x] Test the feature
