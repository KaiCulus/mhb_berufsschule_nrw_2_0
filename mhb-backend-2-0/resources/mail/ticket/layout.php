<?php
/**
 * Basis-Layout für alle Ticket-Benachrichtigungen.
 *
 * Erwartete Variablen:
 *   string      $content    Bereits escapetes HTML des Mail-Inhalts
 *   string|null $ticketUrl  Deep-Link zur Ticketseite (optional)
 */
?>
<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
    <?= $content ?>

    <?php if (!empty($ticketUrl)): ?>
        <p style="margin-top: 28px;">
            <a href="<?= htmlspecialchars($ticketUrl) ?>"
               style="display: inline-block; background: #0e64a6; color: #fff;
                      padding: 12px 24px; border-radius: 6px;
                      text-decoration: none; font-weight: bold;">
                Ticket öffnen
            </a>
        </p>
        <p style="font-size: 0.8rem; color: #888;">
            Falls der Button nicht funktioniert:<br>
            <a href="<?= htmlspecialchars($ticketUrl) ?>" style="color: #0e64a6;">
                <?= htmlspecialchars($ticketUrl) ?>
            </a>
        </p>
    <?php endif; ?>
</div>