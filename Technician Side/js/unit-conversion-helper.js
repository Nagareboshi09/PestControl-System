/**
 * Unit Conversion Helper
 * 
 * This file provides utility functions for handling unit conversions and validations
 * for chemical dosage inputs in the job order system.
 */

/**
 * Format a unit for display
 * @param {string} unit - The unit to format
 * @return {string} The formatted unit
 */
function formatUnit(unit) {
    // Default to ml if no unit is provided
    if (!unit) return 'ml';
    
    // Format common units for better display
    switch(unit.toLowerCase()) {
        case 'ml':
            return 'ml';
        case 'l':
        case 'liter':
        case 'liters':
            return 'L';
        case 'g':
            return 'g';
        case 'kg':
        case 'kilo':
        case 'kilos':
            return 'kg';
        case 'oz':
        case 'ounce':
        case 'ounces':
            return 'oz';
        case 'lb':
        case 'lbs':
        case 'pound':
        case 'pounds':
            return 'lb';
        default:
            // Return the unit as is if not recognized
            return unit;
    }
}

/**
 * Validate a dosage input field
 * @param {HTMLInputElement} input - The input element to validate
 * @return {boolean} Whether the input is valid
 */
function validateDosageInput(input) {
    if (!input) {
        console.error('validateDosageInput called with null or undefined input');
        return false;
    }

    // Store original value for logging
    const originalValue = input.value;
    
    // Remove any non-numeric characters except decimal point
    input.value = input.value.replace(/[^0-9.]/g, '');

    // Ensure only one decimal point
    const parts = input.value.split('.');
    if (parts.length > 2) {
        input.value = parts[0] + '.' + parts.slice(1).join('');
    }

    // Ensure value is not negative
    const value = parseFloat(input.value);
    if (isNaN(value) || value < 0) {
        input.value = '0';
        console.log('Invalid dosage value corrected:', originalValue, '->', input.value);
        return false;
    }

    // Log validation result
    console.log('Dosage validation:', originalValue, '->', input.value, 'Valid:', !isNaN(value) && value >= 0);
    
    return !isNaN(value) && value >= 0;
}

/**
 * Convert a value from one unit to another
 * @param {number} value - The value to convert
 * @param {string} fromUnit - The source unit
 * @param {string} toUnit - The target unit
 * @return {number|boolean} The converted value, or false if conversion is not possible
 */
function convertUnits(value, fromUnit, toUnit) {
    // If units are the same, no conversion needed
    if (fromUnit === toUnit) {
        return value;
    }
    
    // Normalize units to lowercase
    fromUnit = fromUnit.toLowerCase();
    toUnit = toUnit.toLowerCase();
    
    // Volume conversions
    const volumeConversions = {
        'ml': 1,
        'l': 1000,
        'liter': 1000,
        'liters': 1000
    };
    
    // Weight conversions
    const weightConversions = {
        'g': 1,
        'kg': 1000,
        'kilo': 1000,
        'kilos': 1000
    };
    
    // Check if both units are in the same category
    if (volumeConversions[fromUnit] && volumeConversions[toUnit]) {
        return value * (volumeConversions[fromUnit] / volumeConversions[toUnit]);
    }
    
    if (weightConversions[fromUnit] && weightConversions[toUnit]) {
        return value * (weightConversions[fromUnit] / weightConversions[toUnit]);
    }
    
    // If we can't convert between these units, return false
    console.warn(`Cannot convert between units: ${fromUnit} to ${toUnit}`);
    return false;
}

// Make the functions available globally
window.formatUnit = formatUnit;
window.validateDosageInput = validateDosageInput;
window.convertUnits = convertUnits;
