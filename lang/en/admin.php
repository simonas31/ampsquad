<?php

declare(strict_types=1);

return [
    'nav_groups' => [
        'content' => 'Content',
        'communication' => 'Communication',
        'settings' => 'Settings',
    ],

    'locale_switcher' => [
        'lt' => 'Lietuvių',
        'en' => 'English',
    ],

    'fields' => [
        'category' => 'Category',
        'client' => 'Client',
        'parent' => 'Parent category',
        'applies_to' => 'Applies to',
        'name' => 'Name',
        'notifications' => 'Notifications',
        'ip_address' => 'IP address',
        'status' => 'Status',
        'icon' => 'Icon',
        'order' => 'Order',
        'is_active' => 'Active',
        'is_featured' => 'Featured',
        'slug' => 'Slug',
        'title' => 'Title',
        'excerpt' => 'Excerpt',
        'description' => 'Description',
        'published_at' => 'Published at',
        'completed_at' => 'Completed at',
        'location' => 'Location',
        'email' => 'Email',
        'phone' => 'Phone',
        'message' => 'Message',
        'created_at' => 'Created at',
        'video' => 'Video',
        'poster' => 'Poster',
        'featured_image' => 'Featured image',
        'gallery' => 'Gallery',
        'blocks' => 'Content blocks',
        'type' => 'Type',
        'embed_url' => 'Embed URL',
        'tags' => 'Tags',
    ],

    'projects' => [
        'label' => 'Project',
        'plural_label' => 'Projects',
    ],

    'categories' => [
        'label' => 'Category',
        'plural_label' => 'Categories',
        'applies_to_helper' => 'Optional hint for which content type this category is shown for.',
    ],

    'tags' => [
        'label' => 'Tag',
        'plural_label' => 'Tags',
    ],

    'pages' => [
        'label' => 'Page',
        'plural_label' => 'Pages',
        'key' => 'Key',
        'key_helper' => 'Stable identifier hardcoded nav/footer links may reference — changing it can break those links.',
    ],

    'videos' => [
        'label' => 'Video',
        'plural_label' => 'Videos',
        'nav_label' => 'Video Carousel',
        'instagram_url' => 'Instagram post/reel URL',
        'title_helper' => 'Optional caption shown with the video.',
        'poster_helper' => 'Thumbnail shown before the video plays.',
    ],

    'contact_requests' => [
        'label' => 'Contact request',
        'plural_label' => 'Contact requests',
    ],

    'recipients' => [
        'label' => 'Recipient',
        'plural_label' => 'Recipients',
        'notifications_helper' => 'Which system emails this recipient should receive.',
    ],

    'calculator_categories' => [
        'label' => 'Calculator category',
        'plural_label' => 'Calculator categories',
        'icon_helper' => 'Heroicon name, e.g. heroicon-o-bolt.',
        'options_count' => 'Options',
    ],

    'general_settings' => [
        'nav_label' => 'General Settings',
        'facebook_url' => 'Facebook URL',
        'instagram_url' => 'Instagram URL',
        'linkedin_url' => 'LinkedIn URL',
    ],

    'homepage_settings' => [
        'nav_label' => 'Homepage Settings',
        'hero_section' => 'Hero',
        'intro_section' => 'Intro',
        'cta_section' => 'Call to action',
        'title_lt' => 'Title (LT)',
        'title_en' => 'Title (EN)',
        'subtitle_lt' => 'Subtitle (LT)',
        'subtitle_en' => 'Subtitle (EN)',
        'content_lt' => 'Content (LT)',
        'content_en' => 'Content (EN)',
        'button_label_lt' => 'Button label (LT)',
        'button_label_en' => 'Button label (EN)',
    ],

    'enums' => [
        'project_status' => [
            'draft' => 'Draft',
            'published' => 'Published',
        ],
        'contact_request_status' => [
            'new' => 'New',
            'read' => 'Read',
            'archived' => 'Archived',
        ],
        'notification_type' => [
            'contact_form_submitted' => 'Contact form submitted',
            'new_project_published' => 'New project published',
        ],
        'video_type' => [
            'instagram_embed' => 'Instagram embed',
            'upload' => 'Uploaded video',
        ],
    ],

    'content_blocks' => [
        'label' => 'Content block',
        'heading' => 'Heading',
        'heading_lt' => 'Heading (LT)',
        'heading_en' => 'Heading (EN)',
        'level' => 'Level',
        'rich_text' => 'Rich text',
        'content_lt' => 'Content (LT)',
        'content_en' => 'Content (EN)',
        'image' => 'Image',
        'caption_lt' => 'Caption (LT)',
        'caption_en' => 'Caption (EN)',
        'gallery' => 'Gallery',
        'video_embed' => 'Video embed',
        'video_url' => 'Video URL (YouTube, Vimeo, etc.)',
        'quote' => 'Quote',
        'quote_lt' => 'Quote (LT)',
        'quote_en' => 'Quote (EN)',
        'author' => 'Author',
        'author_helper' => 'Not translated — proper nouns stay as-is in both languages.',
        'cta' => 'Call to action',
        'button_label_lt' => 'Button label (LT)',
        'button_label_en' => 'Button label (EN)',
        'button_url' => 'Button URL',
    ],
];
