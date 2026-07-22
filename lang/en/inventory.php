<?php

return [
    'title' => 'Master inventory',
    'section_description' => 'Record of unit furnishings and equipment with photo evidence.',
    'add_item' => 'Add item',
    'edit_item' => 'Edit item',
    'empty' => 'No inventory items yet.',
    'empty_cta' => 'Add the first item to document this unit.',
    'item_name' => 'Item name',
    'quantity' => 'Quantity',
    'condition' => 'Condition',
    'photos' => 'Photos',
    'upload_photo' => 'Upload photos',
    'choose_photo' => 'Choose files',
    'uploading_photo' => 'Uploading photos...',
    'no_photos' => 'No photos',
    'view_photos' => 'View photos',
    'photo_gallery' => 'Photo gallery',
    'photo_gallery_for' => 'Photos of :item',
    'photo_count' => ':count photo|:count photos',
    'photo_viewer' => 'Photo viewer',
    'previous_photo' => 'Previous photo',
    'next_photo' => 'Next photo',
    'close_viewer' => 'Close viewer',
    'delete_photo' => 'Delete photo',
    'delete_photo_title' => 'Delete inventory photo',
    'photo_position' => 'Photo :current of :total',
    'unit_show_title' => ':code · :property',
    'unit_info' => 'Unit details',
    'back_to_units' => 'Back to units',
    'view_inventory' => 'Inventory',

    'conditions' => [
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
    ],

    'validation' => [
        'name_required' => 'Item name is required.',
        'quantity_min' => 'Quantity must be at least 1.',
        'condition_invalid' => 'Select a valid condition.',
        'max_photos' => 'Each item allows up to 5 photos.',
        'photo_required' => 'Select at least one image to upload.',
        'photo_invalid' => 'Only JPG or PNG images up to 5 MB are allowed.',
    ],

    'messages' => [
        'item_created' => 'Item added to inventory.',
        'item_updated' => 'Item updated.',
        'item_deleted' => 'Item removed from inventory.',
        'photo_uploaded' => 'Photos uploaded successfully.',
        'photo_deleted' => 'Photo deleted.',
        'confirm_delete_photo' => 'Delete this inventory photo?',
        'confirm_delete_item' => 'Remove this item from inventory?',
    ],

    'audit' => [
        'photo_uploaded' => 'Inventory photo uploaded for item #:id',
        'photo_deleted' => 'Inventory photo deleted from item #:id',
    ],
];
