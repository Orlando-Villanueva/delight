<?php

return [
    'day_label' => ':label — jour :day',
    'testaments' => ['old' => 'Ancien Testament', 'new' => 'Nouveau Testament', 'deuterocanonical' => 'Livres deutérocanoniques'],
    'plans' => [
        'standard-canonical' => ['name' => 'Ordre canonique', 'description' => 'Lisez la Bible dans l’ordre habituel des livres.'],
        'chronological' => ['name' => 'Ordre chronologique', 'description' => 'Lisez les passages bibliques selon l’ordre des événements.'],
        'mcheyne' => ['name' => 'Plan de lecture de McCheyne', 'description' => 'Suivez le plan de lecture biblique de Robert Murray McCheyne.'],
        'catholic-canonical' => ['name' => 'Ordre canonique catholique', 'description' => 'Lisez la Bible selon le canon catholique.'],
    ],
    'plan_messages' => [
        'subscribed' => 'Vous suivez maintenant le plan :plan.',
        'unsubscribed' => 'Vous ne suivez plus le plan :plan.',
        'resumed' => 'Vous avez repris le plan :plan.',
    ],
    'errors' => [
        'subscription_missing' => 'Abonnement introuvable.',
        'subscribe_first' => 'Abonnez-vous à ce plan pour consulter la lecture du jour.',
        'plan_unavailable' => 'Ce plan n’est pas offert avec le canon choisi.',
        'inactive_subscription' => 'Impossible d’ajouter une lecture avec un abonnement inactif.',
        'before_tracking' => 'Impossible d’ajouter une journée antérieure au début du suivi.',
        'invalid_day' => 'Journée de lecture invalide.',
        'day_complete' => 'Cette journée du plan est déjà terminée.',
        'enable_catholic_canon' => 'Activez les livres deutérocanoniques dans les réglages pour utiliser ce plan.',
        'invalid_chapter_range' => 'La plage de chapitres ne correspond pas au livre choisi.',
        'duplicate_reading' => 'Vous avez déjà enregistré un ou plusieurs de ces chapitres pour :date.',
    ],
    'settings' => [
        'saved' => 'Réglages enregistrés.',
        'saved_plan_paused' => 'Réglages enregistrés. Votre plan catholique a été suspendu.',
    ],
    'feedback' => [
        'sent' => 'Merci! Votre commentaire a été envoyé.',
        'failed' => 'Votre commentaire n’a pas pu être envoyé. Veuillez réessayer.',
    ],
    'reading' => [
        'note_updated' => 'La note de lecture a été mise à jour.',
        'deleted' => 'La lecture a été supprimée.',
        'selected_deleted' => 'Les lectures sélectionnées ont été supprimées.',
        'unauthorized_delete' => 'Vous n’êtes pas autorisé à supprimer cette lecture.',
    ],
    'time_ago' => [
        'just_now' => 'À l’instant',
        'minute' => 'Il y a :count minute',
        'minutes' => 'Il y a :count minutes',
        'hour' => 'Il y a :count heure',
        'hours' => 'Il y a :count heures',
        'day' => 'Il y a :count jour',
        'days' => 'Il y a :count jours',
    ],
];
