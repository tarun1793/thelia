<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Thelia\Install\Exception\UpdateException;

$context = 'update';
$step = 2;

include("header.php");

// todo: check security

// Retrieve the website url
$url = $_SERVER['PHP_SELF'];
$website_url = preg_replace("#/install/[a-z](.*)#" ,'', $url);

$backup = (isset($_GET['backup']) && $_GET['backup'] == 1);

?>
    <div class="well">

        <p class="lead text-center">
            <?php echo $trans->trans('Updating Thelia.'); ?>
        </p>

        <?php
        $update = new \Thelia\Install\Update(false);
        if ($update->isLatestVersion()) { ?>

            <div class="alert alert-warning">
                <p><?php
                    echo $trans->trans('It seems that Thelia database is already up to date.');
                    ?></p>
            </div>

        <?php } else {

            $updateError = null;
            $continue = true;

            // Backup
            if ($backup) {
                if (false === $update->backupDb()) {
                    $continue = false ;
                    ?>
                    <div class="alert alert-danger">
                        <p><?php
                            echo $trans->trans(
                                'Sorry, your database can\'t be backed up. Try to do it manually'
                            );
                            ?></p>
                    </div><?php
                } else {
                    ?>
                    <div class="alert alert-success">
                    <p><?php
                        echo $trans->trans(
                            'Your database has been backed up. The sql file : %file',
                            [
                                '%file' => $update->getBackupFile()
                            ]
                        );
                        ?></p>
                    </div><?php
                }
            }

            if ($continue) {
                try {
                    $update->process();
                } catch (UpdateException $ex) {
                    $updateError = $ex;
                }

                if (null === $updateError) {
                    ?>

                    <div class="alert alert-success">
                        <p><?php
                            echo $trans->trans(
                                'Thelia as been successfully updated to version %version',
                                ['%version' => $update->getCurrentVersion()]
                            );
                            ?></p>
                    </div>

                    <p class="lead text-center">
                        <a href="<?php echo $website_url; ?>/index.php/admin"
                           id="admin_url"><?php echo $trans->trans('Go to back office'); ?></a>
                    </p>

                <?php } else { ?>
                    <div class="alert alert-danger">
                        <?php echo $trans->trans(
                            '<p><strong>Sorry, an unexpected error occured</strong>: %err</p><p>Error details:</p><p>%details</p>',
                            [
                                '%err' => $updateError->getMessage(),
                                '%details' => nl2br($updateError->getTraceAsString())
                            ]
                        ); ?>
                    </div>
                <?php
                    // Try to restore DB
                    if ($backup) {
                        if (false === $update->restoreDb()) {
                            $continue = false ;
                            ?>
                            <div class="alert alert-danger">
                            <p><?php
                                echo $trans->trans(
                                    'Sorry, your database can\'t be restored. Try to do it manually'
                                );
                                ?></p>
                            <p><?php
                                echo $trans->trans(
                                    'The sql dump has been saved in %file',
                                    [
                                        '%file' => $update->getBackupFile()
                                    ]
                                );
                                ?></p>
                            </div><?php
                        } else {
                            ?>
                            <div class="alert alert-success">
                            <p><?php
                                echo $trans->trans(
                                    'Your database has been restored.'
                                );
                                ?></p>
                            </div><?php
                        }
                    }
                } ?>

                <p class="lead"><?php echo $trans->trans('Update proccess'); ?></p>
                <ul class="list-unstyled list-group">
                    <?php foreach ($update->getUpdatedVersions() as $version) { ?>
                        <li class="list-group-item text-success"><?php
                            echo $trans->trans("update to version %version", ['%version' => $version]);
                            ?></li>
                    <?php
                    }

                    if (null !== $updateError) {
                        ?>
                        <li class="list-group-item text-danger"><?php
                            echo $trans->trans("update to version %version",
                                ['%version' => $updateError->getVersion()]);
                            ?></li>
                    <?php } ?>
                </ul>
                <?php

                if (null !== $updateError) {
                    ?>
                    <p class="lead"><?php echo $trans->trans('Update proccess trace'); ?></p>
                    <ul class="list-unstyled list-group">
                        <?php foreach ($update->getLogs() as $log) { ?>
                            <li class="list-group-item"><?php
                                echo sprintf("[%s] %s", $log[0], $log[1]);
                                ?></li>
                        <?php } ?>
                    </ul>
                <?php
                } else {

                    $finder = new Finder();
                    $fs = new Filesystem();
                    $hasDeleteError = false;

                    // try to clear cache
                    $finder->files()->in(THELIA_CACHE_DIR);

                    foreach ($finder as $file) {
                        try {
                            $fs->remove($file);
                        } catch (\Symfony\Component\Filesystem\Exception\IOException $ex) {
                            $hasDeleteError = true;
                        }
                    }

                    if ($hasDeleteError) { ?>
                        <div class="alert alert-danger"><p><?php
                            echo $trans->trans('Cache directory has not been cleared. Please manually delete content of cache directory.');
                        ?></p></div>
                    <?php } else { ?>
                        <div class="alert alert-success"><p><?php
                            echo $trans->trans('Cache directory has been cleared');
                            ?></p></div>
                    <?php } ?>

                    <div class="alert alert-success"><p><?php
                        echo $trans->trans('The update wizard directory will be removed');
                    ?></p></div><?php

                }

            }
        }
        ?>

    </div>
<?php

include("footer.php");

// Remove the update wizard
if (null === $updateError) {
    try {
        $fs->remove(THELIA_WEB_DIR . DS . 'install');
    } catch (\Symfony\Component\Filesystem\Exception\IOException $ex) {
        ;
    }
}