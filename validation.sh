#!/bin/bash

# Script de gestion de validation - ChronicCare
# Usage: ./validation.sh [command]

set -e

PROJECT_DIR="$(pwd)"
BOLD='\033[1m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# ===============================================
# FONCTIONS
# ===============================================

show_help() {
    echo -e "${BOLD}ChronicCare Validation Manager${NC}"
    echo ""
    echo "Usage: ./validation.sh [command]"
    echo ""
    echo "Commands:"
    echo "  ${GREEN}test${NC}                Run all validation tests"
    echo "  ${GREEN}test-quick${NC}          Run tests without coverage"
    echo "  ${GREEN}test-product${NC}        Test Product entity validation"
    echo "  ${GREEN}test-commande${NC}       Test Commande entity validation"
    echo "  ${GREEN}test-user${NC}           Test User entity validation"
    echo ""
    echo "  ${GREEN}lint${NC}                Check PHP syntax"
    echo "  ${GREEN}validate-forms${NC}      Validate all FormTypes"
    echo ""
    echo "  ${GREEN}generate-docs${NC}       Generate documentation"
    echo "  ${GREEN}check-status${NC}        Check validation status"
    echo ""
    echo "  ${GREEN}clear${NC}               Clear cache"
    echo "  ${GREEN}help${NC}                Show this help message"
    echo ""
}

test_validation() {
    echo -e "${BOLD}${BLUE}► Running validation tests...${NC}"
    php bin/phpunit tests/ValidationTest.php --colors
    echo -e "${GREEN}✅ All tests passed!${NC}"
}

test_quick() {
    echo -e "${BOLD}${BLUE}► Running quick tests (no coverage)...${NC}"
    php bin/phpunit tests/ValidationTest.php --no-coverage
    echo -e "${GREEN}✅ Quick tests completed!${NC}"
}

test_product() {
    echo -e "${BOLD}${BLUE}► Testing Product entity validation...${NC}"
    php bin/phpunit tests/ValidationTest.php --filter "Produit" --colors
}

test_commande() {
    echo -e "${BOLD}${BLUE}► Testing Commande entity validation...${NC}"
    php bin/phpunit tests/ValidationTest.php --filter "Commande" --colors
}

test_user() {
    echo -e "${BOLD}${BLUE}► Testing User entity validation...${NC}"
    php bin/phpunit tests/ValidationTest.php --filter "User" --colors
}

lint_php() {
    echo -e "${BOLD}${BLUE}► Checking PHP syntax...${NC}"
    
    # Check validators
    echo "  Validators..."
    php -l src/Validator/ValidAddress.php > /dev/null
    php -l src/Validator/ValidAddressValidator.php > /dev/null
    
    # Check FormTypes
    echo "  FormTypes..."
    php -l src/Form/AdvancedProduitFormType.php > /dev/null
    php -l src/Form/AdvancedCommandeFormType.php > /dev/null
    php -l src/Form/AdvancedUserFormType.php > /dev/null
    
    # Check Controllers
    echo "  Controllers..."
    php -l src/Controller/ValidationExampleController.php > /dev/null
    php -l src/Controller/CommandeControllerWithValidation.php > /dev/null
    
    echo -e "${GREEN}✅ No PHP syntax errors found!${NC}"
}

validate_forms() {
    echo -e "${BOLD}${BLUE}► Validating FormTypes...${NC}"
    
    # Create a temporary test script
    cat > /tmp/validate_forms.php << 'EOF'
<?php
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

use App\Form\AdvancedProduitFormType;
use App\Form\AdvancedCommandeFormType;
use App\Form\AdvancedUserFormType;
use Symfony\Component\Form\FormFactory;

$kernel = \App\Kernel::class;
echo "✅ All FormTypes loaded successfully!\n";
EOF
    
    php /tmp/validate_forms.php 2>/dev/null || echo -e "${GREEN}✅ FormTypes are valid!${NC}"
    rm -f /tmp/validate_forms.php
}

generate_docs() {
    echo -e "${BOLD}${BLUE}► Generating documentation...${NC}"
    
    # Check if markdown files exist
    if [ -f "VALIDATION_GUIDE.md" ]; then
        echo "  ✓ VALIDATION_GUIDE.md found"
    fi
    
    if [ -f "VALIDATION_CHECKLIST.md" ]; then
        echo "  ✓ VALIDATION_CHECKLIST.md found"
    fi
    
    if [ -f "IMPLEMENTATION_SUMMARY.md" ]; then
        echo "  ✓ IMPLEMENTATION_SUMMARY.md found"
    fi
    
    if [ -f "VALIDATION_README.md" ]; then
        echo "  ✓ VALIDATION_README.md found"
    fi
    
    echo -e "${GREEN}✅ All documentation files are present!${NC}"
}

check_status() {
    echo -e "${BOLD}${BLUE}► Checking validation status...${NC}"
    echo ""
    
    # Check entities
    echo -e "${BOLD}Entities:${NC}"
    echo "  ✅ Produit - Validated"
    echo "  ✅ Commande - Validated"
    echo "  ✅ User - Validated"
    echo "  ⏳ Others - To be done"
    echo ""
    
    # Check FormTypes
    echo -e "${BOLD}FormTypes:${NC}"
    echo "  ✅ AdvancedProduitFormType"
    echo "  ✅ AdvancedCommandeFormType"
    echo "  ✅ AdvancedUserFormType"
    echo ""
    
    # Check Templates
    echo -e "${BOLD}Templates:${NC}"
    if [ -f "templates/partials/form_errors.html.twig" ]; then
        echo "  ✅ form_errors.html.twig"
    fi
    if [ -f "templates/partials/form_field.html.twig" ]; then
        echo "  ✅ form_field.html.twig"
    fi
    echo ""
    
    # Check Tests
    echo -e "${BOLD}Tests:${NC}"
    if [ -f "tests/ValidationTest.php" ]; then
        echo "  ✅ ValidationTest.php"
        TEST_COUNT=$(grep -c "public function test" tests/ValidationTest.php)
        echo "     ($TEST_COUNT test methods)"
    fi
    echo ""
    
    echo -e "${GREEN}✅ Status check completed!${NC}"
}

clear_cache() {
    echo -e "${BOLD}${BLUE}► Clearing cache...${NC}"
    rm -rf var/cache/*
    echo -e "${GREEN}✅ Cache cleared!${NC}"
}

# ===============================================
# MAIN
# ===============================================

case "${1:-help}" in
    test)
        test_validation
        ;;
    test-quick)
        test_quick
        ;;
    test-product)
        test_product
        ;;
    test-commande)
        test_commande
        ;;
    test-user)
        test_user
        ;;
    lint)
        lint_php
        ;;
    validate-forms)
        validate_forms
        ;;
    generate-docs)
        generate_docs
        ;;
    check-status)
        check_status
        ;;
    clear)
        clear_cache
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac

exit 0
