<?php

/**
 * French translation catalog — Toys4Us Toy Store.
 */

return [
    // ── Layout chrome ─────────────────────────────────────────────────────────
    'app.title'            => 'Toys4Us',
    'app.subtitle'         => 'Votre guichet unique pour le plaisir et le jeu !',
    'app.footer'           => 'Toys4Us - eCommerce Lab10 - Hiver 2026',
    'app.lang.en'          => 'EN',
    'app.lang.fr'          => 'FR',
    'app.logout'           => 'Se déconnecter',
    'app.cart'             => 'Panier',

    // ── Products page ─────────────────────────────────────────────────────────
    'products.heading'     => 'Nos Jouets',
    'products.subheading'  => 'Parcourez notre collection de jouets amusants pour tous les âges !',
    'products.view'        => 'Voir',
    'products.add_to_cart' => 'Ajouter au Panier',
    'products.back'        => 'Retour aux Jouets',

    // ── Cart page ──────────────────────────────────────────────────────────────
    'cart.title'            => 'Panier d\'achat',
    'cart.empty'            => 'Votre panier est vide.',
    'cart.continue_shopping'=> 'Continuer vos achats',
    'cart.product'          => 'Produit',
    'cart.qty'              => 'Qté',
    'cart.price'            => 'Prix',
    'cart.total'            => 'Total',
    'cart.grand_total'      => 'Total Général',
    'cart.checkout'         => 'Commander',

    // ── Auth — step 1: username entry ──────────────────────────────────────────
    'auth.title'           => 'Se connecter',
    'auth.username_label'  => 'Nom d\'utilisateur',
    'auth.username_ph'     => 'Entrez votre nom d\'utilisateur',
    'auth.request_otp'     => 'Envoyer le code',

    // ── Auth — step 2: TOTP setup ─────────────────────────────────────────────
    'auth.otp_sent'        => 'Scannez ce code avec votre application d\'authentification',
    'auth.otp_note'        => 'Ouvrez Google Authenticator, Authy, ou toute autre application TOTP et scannez le code QR. Puis cliquez sur Continuer.',
    'auth.continue'        => 'Continuer vers la vérification',

    // ── Auth — step 3: OTP entry ───────────────────────────────────────────────
    'auth.verify_title'    => 'Entrez votre code',
    'auth.otp_label'       => 'Mot de passe à usage unique',
    'auth.otp_ph'          => 'Code à 6 chiffres',
    'auth.verify'          => 'Vérifier',
    'auth.error_invalid'   => 'Code invalide ou expiré. Veuillez réessayer.',
    'auth.error_expired'   => 'Votre code a expiré. Veuillez en demander un nouveau.',
];