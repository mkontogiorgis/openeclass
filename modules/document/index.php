<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2012  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ======================================================================== */

$is_in_tinymce = (isset($_REQUEST['embedtype']) && $_REQUEST['embedtype'] == 'tinymce') ? true : false;

if (!defined('COMMON_DOCUMENTS')) {
    $require_current_course = TRUE;
    $menuTypeID = ($is_in_tinymce) ? 5 : 2;
} else {
    if ($is_in_tinymce) {
        $menuTypeID = 5;
    } else {
        $require_admin = TRUE;
        $menuTypeID = 3;
    }
}

$guest_allowed = true;
require_once '../../include/baseTheme.php';
/* * ** The following is added for statistics purposes ** */
require_once 'include/action.php';
$action = new action();
require_once 'doc_init.php';
require_once 'doc_metadata.php';
require_once 'include/lib/forcedownload.php';
require_once 'include/lib/fileDisplayLib.inc.php';
require_once 'include/lib/fileManageLib.inc.php';
require_once 'include/lib/fileUploadLib.inc.php';
require_once 'include/pclzip/pclzip.lib.php';
require_once 'include/lib/modalboxhelper.class.php';
require_once 'include/lib/multimediahelper.class.php';
require_once 'include/lib/mediaresource.factory.php';
require_once 'modules/search/documentindexer.class.php';
require_once 'include/log.php';

load_js('tools.js');
ModalBoxHelper::loadModalBox(true);
copyright_info_init();

$require_help = TRUE;
$helpTopic = 'Doc';

if ($is_in_tinymce) {
    $_SESSION['embedonce'] = true; // necessary for baseTheme
    $docsfilter = (isset($_REQUEST['docsfilter'])) ? 'docsfilter=' . $_REQUEST['docsfilter'] . '&amp;' : '';
    $base_url .= 'embedtype=tinymce&amp;' . $docsfilter;
    load_js('jquery');
    load_js('tinymce.popup.urlgrabber.min.js');
}

// check for quotas
$diskUsed = dir_total_space($basedir);
if (defined('COMMON_DOCUMENTS')) {
    $diskQuotaDocument = $diskUsed + ini_get('upload_max_filesize') * 1024 * 1024;
} else {
    $type = ($subsystem == GROUP) ? 'group_quota' : 'doc_quota';
    $d = Database::get()->querySingle("SELECT $type as quotatype FROM course WHERE id = ?d", $course_id);
    $diskQuotaDocument = $d->quotatype;
}


if (isset($_GET['showQuota'])) {
    $nameTools = $langQuotaBar;
    if ($subsystem == GROUP) {
        $navigation[] = array('url' => 'index.php?course=' . $course_code . '&amp;group_id=' . $group_id, 'name' => $langDoc);
    } elseif ($subsystem == EBOOK) {
        $navigation[] = array('url' => 'index.php?course=' . $course_code . '&amp;ebook_id=' . $ebook_id, 'name' => $langDoc);
    } elseif ($subsystem == COMMON) {
        $navigation[] = array('url' => 'commondocs.php', 'name' => $langCommonDocs);
    } else {
        $navigation[] = array('url' => 'index.php?course=' . $course_code, 'name' => $langDoc);
    }
    $tool_content .= showquota($diskQuotaDocument, $diskUsed);
    draw($tool_content, $menuTypeID);
    exit;
}

if ($subsystem == EBOOK) {
    $nameTools = $langFileAdmin;
    $navigation[] = array('url' => 'index.php?course=' . $course_code, 'name' => $langEBook);
    $navigation[] = array('url' => 'edit.php?course=' . $course_code . '&amp;id=' . $ebook_id, 'name' => $langEBookEdit);
}

// ---------------------------
// download directory or file
// ---------------------------
if (isset($_GET['download'])) {
    $downloadDir = $_GET['download'];

    if ($downloadDir == '/') {
        $format = '.dir';
        $real_filename = remove_filename_unsafe_chars($langDoc . ' ' . $public_code);
    } else {
        $q = Database::get()->querySingle("SELECT filename, format, visible, extra_path FROM document
                        WHERE $group_sql AND
                        path = ?s", $downloadDir);
        if (!$q) {
            not_found($downloadDir);
        }
        $real_filename = $q->filename;
        $format = $q->format;
        $visible = $q->visible;
        $extra_path = $q->extra_path;
        if (!$visible) {
            not_found($downloadDir);
        }
    }
    // Allow unlimited time for creating the archive
    set_time_limit(0);

    if ($format == '.dir') {
        $real_filename = $real_filename . '.zip';
        $dload_filename = $webDir . '/courses/temp/' . safe_filename('zip');
        zip_documents_directory($dload_filename, $downloadDir, $can_upload);
        $delete = true;
    } elseif ($extra_path) {
        if ($real_path = common_doc_path($extra_path, true)) {
            // Common document
            if (!$common_doc_visible) {
                forbidden($downloadDir);
            }
            $dload_filename = $real_path;
            $delete = false;
        } else {
            // External document - redirect to URL
            header('Location: ' . $extra_path);
            exit;
        }
    } else {
        $dload_filename = $basedir . $downloadDir;
        $delete = false;
    }

    send_file_to_client($dload_filename, $real_filename, null, true, $delete);
    exit;
}

/**
 * Used in documents path navigation bar
 * @global type $langRoot
 * @global type $base_url
 * @global type $group_sql
 * @param type $path
 * @return type
 */
function make_clickable_path($path) {
    global $langRoot, $base_url, $group_sql;

    $cur = $out = '';
    foreach (explode('/', $path) as $component) {
        if (empty($component)) {
            $out = "<a href='{$base_url}openDir=/'>$langRoot</a>";
        } else {
            $cur .= rawurlencode("/$component");
            $row = Database::get()->querySingle("SELECT filename FROM document
                                        WHERE path LIKE '%/$component' AND $group_sql");
            $dirname = $row->filename;
            $out .= " &raquo; <a href='{$base_url}openDir=$cur'>".q($dirname)."</a>";
        }
    }
    return $out;
}

if ($can_upload) {
    $error = false;
    $uploaded = false;
    if (isset($_POST['uploadPath'])) {
        $uploadPath = str_replace('\'', '', $_POST['uploadPath']);
    } else {
        $uploadPath = '';
    }
    // Check if upload path exists
    if (!empty($uploadPath)) {
        $result = Database::get()->querySingle("SELECT count(*) as total FROM document
                        WHERE $group_sql AND
                        path = ?s", $uploadPath);
        if (!$result || !$result->total) {
            $error = $langImpossible;
        }
    }

    /*     * *******************************************************************
      UPLOAD FILE

      Ousiastika dhmiourgei ena safe_fileName xrhsimopoiwntas ta DATETIME
      wste na mhn dhmiourgeitai provlhma sto filesystem apo to onoma tou
      arxeiou. Parola afta to palio filename pernaei apo 'filtrarisma' wste
      na apofefxthoun 'epikyndynoi' xarakthres.
     * ********************************************************************* */

    $didx = new DocumentIndexer();

    $action_message = $dialogBox = '';
    if (isset($_FILES['userFile']) and is_uploaded_file($_FILES['userFile']['tmp_name'])) {
        validateUploadedFile($_FILES['userFile']['name'], $menuTypeID);
        $extra_path = '';
        $userFile = $_FILES['userFile']['tmp_name'];
        // check for disk quotas
        $diskUsed = dir_total_space($basedir);
        if ($diskUsed + @$_FILES['userFile']['size'] > $diskQuotaDocument) {
            $action_message .= "<p class='caution'>$langNoSpace</p>";
        } else {
            if (unwanted_file($_FILES['userFile']['name'])) {
                $action_message .= "<p class='caution'>$langUnwantedFiletype: " .
                        q($_FILES['userFile']['name']) . "</p>";
            } elseif (isset($_POST['uncompress']) and $_POST['uncompress'] == 1 and preg_match('/\.zip$/i', $_FILES['userFile']['name'])) {
                /*                 * * Unzipping stage ** */
                $zipFile = new pclZip($userFile);
                validateUploadedZipFile($zipFile->listContent(), $menuTypeID);
                $realFileSize = 0;
                $zipFile->extract(PCLZIP_CB_PRE_EXTRACT, 'process_extracted_file');
                if ($diskUsed + $realFileSize > $diskQuotaDocument) {
                    $action_message .= "<p class='caution'>$langNoSpace</p>";
                } else {
                    $action_message .= "<p class='success'>$langDownloadAndZipEnd</p><br />";
                }
            } else {
                $fileName = canonicalize_whitespace($_FILES['userFile']['name']);
                $uploaded = true;
            }
        }
    } elseif (isset($_POST['fileURL']) and ( $fileURL = trim($_POST['fileURL']))) {
        $extra_path = canonicalize_url($fileURL);
        if (preg_match('/^javascript/', $extra_path)) {
            $action_message .= "<p class='caution'>$langUnwantedFiletype: " .
                    q($extra_path) . "</p>";
        } else {
            $uploaded = true;
        }
        $components = explode('/', $extra_path);
        $fileName = end($components);
    }
    if ($uploaded) {
        // Check if file already exists
        $result = Database::get()->querySingle("SELECT path, visible FROM document WHERE
                                           $group_sql AND
                                           path REGEXP ?s AND
                                           filename = ?s LIMIT 1",
                                        "^$uploadPath/[^/]+$", $fileName);
        if ($result) {
            if (isset($_POST['replace'])) {
                // Delete old file record when replacing file
                $file_path = $result->path;
                $vis = $result->visible;
                Database::get()->query("DELETE FROM document WHERE
                                                 $group_sql AND
                                                 path = ?s", $file_path);
            } else {
                $error = $langFileExists;
            }
        }
    }
    if ($error) {
        $action_message .= "<p class='caution'>$error</p><br />";
    } elseif ($uploaded) {
        // No errors, so proceed with upload
        // Try to add an extension to files witout extension,
        // change extension of PHP files
        $fileName = php2phps(add_ext_on_mime($fileName));
        // File name used in file system and path field
        $safe_fileName = safe_filename(get_file_extension($fileName));
        if ($uploadPath == '.') {
            $file_path = '/' . $safe_fileName;
        } else {
            $file_path = $uploadPath . '/' . $safe_fileName;
        }
        $vis = 1;
        $file_format = get_file_extension($fileName);
        // File date is current date
        $file_date = date("Y\-m\-d G\:i\:s");
        if ($extra_path or ( isset($userFile) and @ copy($userFile, $basedir . $file_path))) {
            $id = Database::get()->query("INSERT INTO document SET
                                        course_id = ?d,
                                        subsystem = ?d,
                                        subsystem_id = ?d,
                                        path = ?s,
                                        extra_path = ?s,
                                        filename = ?s,
                                        visible = ?d,
                                        comment = ?s,
                                        category = ?d,
                                        title = ?s,
                                        creator = ?s,
                                        date = ?t,
                                        date_modified = ?t,
                                        subject = ?s,
                                        description = ?s,
                                        author = ?s,
                                        format = ?s,
                                        language = ?s,
                                        copyrighted = ?d"
                            , $course_id, $subsystem, $subsystem_id, $file_path, $extra_path, $fileName, $vis
                            , $_POST['file_comment'], $_POST['file_category'], $_POST['file_title'], $_POST['file_creator']
                            , $file_date, $file_date, $_POST['file_subject'], $_POST['file_description'], $_POST['file_author']
                            , $file_format, $_POST['file_language'], $_POST['file_copyrighted'])->lastInsertID;
            $didx->store($id);
            // Logging
            Log::record($course_id, MODULE_ID_DOCS, LOG_INSERT, array('id' => $id,
                'filepath' => $file_path,
                'filename' => $fileName,
                'comment' => $_POST['file_comment'],
                'title' => $_POST['file_title']));
            $action_message .= "<p class='success'>$langDownloadEnd</p><br />";
        } else {
            // Moving uploaded file failed
            $action_message .= "<p class='caution'>$error</p><br />";
        }
    }

    /*     * ************************************
      MOVE FILE OR DIRECTORY
     * ************************************ */
    /* -------------------------------------
      MOVE FILE OR DIRECTORY : STEP 2
      -------------------------------------- */
    if (isset($_POST['moveTo'])) {
        $moveTo = $_POST['moveTo'];
        $source = $_POST['source'];
        $sourceXml = $source . '.xml';
        //check if source and destination are the same
        if ($basedir . $source != $basedir . $moveTo or $basedir . $source != $basedir . $moveTo) {
            $r = Database::get()->querySingle("SELECT filename, extra_path FROM document WHERE $group_sql AND path=?s", $source);
            $filename = $r->filename;
            $extra_path = $r->extra_path;
            if (empty($extra_path)) {
                if (move($basedir . $source, $basedir . $moveTo)) {
                    if (hasMetaData($source, $basedir, $group_sql)) {
                        move($basedir . $sourceXml, $basedir . $moveTo);
                    }
                    update_db_info('document', 'update', $source, $filename, $moveTo . '/' . my_basename($source));
                }
            } else {
                update_db_info('document', 'update', $source, $filename, $moveTo . '/' . my_basename($source));
            }
            $action_message = "<p class='success'>$langDirMv</p><br />";
        } else {
            $action_message = "<p class='caution'>$langImpossible</p><br />";
            /*             * * return to step 1 ** */
            $move = $source;
            unset($moveTo);
        }
    }

    /* -------------------------------------
      MOVE FILE OR DIRECTORY : STEP 1
      -------------------------------------- */
    if (isset($_GET['move'])) {
        $move = $_GET['move'];
        // h $move periexei to onoma tou arxeiou. anazhthsh onomatos arxeiou sth vash
        $moveFileNameAlias = Database::get()->querySingle("SELECT filename FROM document
                                                WHERE $group_sql AND path=?s", $move)->filename;
        $dialogBox .= directory_selection($move, 'moveTo', dirname($move));
    }

    /*     * ************************************
      DELETE FILE OR DIRECTORY
     * ************************************ */
    if (isset($_POST['delete']) or isset($_POST['delete_x'])) {
        $delete = str_replace('..', '', $_POST['filePath']);
        // Check if file actually exists
        $r = Database::get()->querySingle("SELECT path, extra_path, format, filename FROM document
                                        WHERE $group_sql AND path = ?s", $delete);
        $delete_ok = true;
        if ($r) {
            // remove from index if relevant (except non-main sysbsystems and metadata)
            Database::get()->queryFunc("SELECT id FROM document WHERE course_id >= 1 AND subsystem = 0
                                            AND format <> '.meta' AND path LIKE ?s",
                function ($r2) use($didx) {
                    $didx->remove($r2->id);
                },
                $delete . '%');

            if (empty($r->extra_path)) {
                if ($delete_ok = my_delete($basedir . $delete) && $delete_ok) {
                    if (hasMetaData($delete, $basedir, $group_sql)) {
                        $delete_ok = my_delete($basedir . $delete . ".xml") && $delete_ok;
                    }
                    update_db_info('document', 'delete', $delete, $r->filename);
                }
            } else {
                update_db_info('document', 'delete', $delete, $r->filename);
            }
            if ($delete_ok) {
                $action_message = "<p class='success'>$langDocDeleted</p><br />";
            } else {
                $action_message = "<p class='caution'>$langGeneralError</p><br />";
            }
        }
    }

    /*     * ***************************************
      RENAME
     * **************************************** */
    // Step 2: Rename file by updating record in database
    if (isset($_POST['renameTo'])) {

        $r = Database::get()->querySingle("SELECT id, filename, format FROM document WHERE $group_sql AND path = ?s", $_POST['sourceFile']);

        if ($r->format != '.dir')
            validateRenamedFile($_POST['renameTo'], $menuTypeID);

        Database::get()->query("UPDATE document SET filename= ?s, date_modified=NOW()
                          WHERE $group_sql AND path=?s"
                , $_POST['renameTo'], $_POST['sourceFile']);
        $didx->store($r->id);
        Log::record($course_id, MODULE_ID_DOCS, LOG_MODIFY, array('path' => $_POST['sourceFile'],
            'filename' => $r->filename,
            'newfilename' => $_POST['renameTo']));
        if (hasMetaData($_POST['sourceFile'], $basedir, $group_sql)) {
            if (Database::get()->query("UPDATE document SET filename=?s WHERE $group_sql AND path = ?s"
                            , ($_POST['renameTo'] . '.xml'), ($_POST['sourceFile'] . '.xml'))->affectedRows > 0) {
                metaRenameDomDocument($basedir . $_POST['sourceFile'] . '.xml', $_POST['renameTo']);
            }
        }
        $action_message = "<p class='success'>$langElRen</p><br />";
    }

    // Step 1: Show rename dialog box
    if (isset($_GET['rename'])) {
        $fileName = Database::get()->querySingle("SELECT filename FROM document
                                             WHERE $group_sql AND
                                                   path = ?s", $_GET['rename'])->filename;
        $dialogBox .= "
                <form method='post' action='$_SERVER[SCRIPT_NAME]?course=$course_code'>
                <fieldset>
                  <legend>$langRename:</legend>
                  <input type='hidden' name='sourceFile' value='" . q($_GET['rename']) . "' />
                  $group_hidden_input
                  <table class='tbl' width='100%'>
                    <tr>
                      <td><b>" . q($fileName) . "</b> $langIn:
                        <input type='text' name='renameTo' value='" . q($fileName) . "' size='50' /></td>
                      <td class='right'><input type='submit' value='$langRename' /></td>
                    </tr>
                  </table>
                </fieldset>
                </form>";
    }

    // create directory
    // step 2: create the new directory
    if (isset($_POST['newDirPath'])) {
        $newDirName = canonicalize_whitespace($_POST['newDirName']);
        if (!empty($newDirName)) {
            $newDirPath = make_path($_POST['newDirPath'], array($newDirName));
            // $path_already_exists: global variable set by make_path()
            if ($path_already_exists) {
                $action_message = "<p class='caution'>$langFileExists</p>";
            } else {
                $r = Database::get()->querySingle("SELECT id FROM document WHERE $group_sql AND path = ?s", $newDirPath);
                $didx->store($r->id);
                $action_message = "<p class='success'>$langDirCr</p>";
            }
        }
    }

    // step 1: display a field to enter the new dir name
    if (isset($_GET['createDir'])) {
        $createDir = q($_GET['createDir']);
        $dialogBox .= "<form action='$_SERVER[SCRIPT_NAME]?course=$course_code' method='post'>
                $group_hidden_input
                <fieldset>
                        <input type='hidden' name='newDirPath' value='$createDir' />
                        <table class='tbl' width='100%'>
                        <tr>
                                <th>$langNameDir</th>
                                <td width='190'><input type='text' name='newDirName' /></td>
                                <td><input type='submit' value='$langCreateDir' /></td>
                        </tr>
                        </table>
                </fieldset>
                </form>
                <br />\n";
    }

    // add/update/remove comment
    if (isset($_POST['commentPath'])) {
        $commentPath = $_POST['commentPath'];
        // check if file exists
        $res = Database::get()->querySingle("SELECT * FROM document
                                             WHERE $group_sql AND
                                                   path=?s", $commentPath);
        if ($res) {
            $file_language = validate_language_code($_POST['file_language'], $language);
            Database::get()->query("UPDATE document SET
                                                comment = ?s,
                                                category = ?d,
                                                title = ?s,
                                                date_modified = NOW(),
                                                subject = ?s,
                                                description = ?s,
                                                author = ?s,
                                                language = ?s,
                                                copyrighted = ?d
                                        WHERE $group_sql AND
                                              path = ?s"
                    , $_POST['file_comment'], $_POST['file_category'], $_POST['file_title'], $_POST['file_subject']
                    , $_POST['file_description'], $_POST['file_author'], $file_language, $_POST['file_copyrighted'], $commentPath);
            $didx->store($res->id);
            Log::record($course_id, MODULE_ID_DOCS, LOG_MODIFY, array('path' => $commentPath,
                'filename' => $res->filename,
                'comment' => $_POST['file_comment'],
                'title' => $_POST['file_title']));
            $action_message = "<p class='success'>$langComMod</p>";
        }
    }

    // add/update/remove metadata
    // h $metadataPath periexei to path tou arxeiou gia to opoio tha epikyrwthoun ta metadata
    if (isset($_POST['metadataPath'])) {

        $metadataPath = $_POST['metadataPath'] . ".xml";
        $oldFilename = $_POST['meta_filename'] . ".xml";
        $xml_filename = $basedir . str_replace('/..', '', $metadataPath);
        $xml_date = date("Y\-m\-d G\:i\:s");
        $file_format = ".meta";

        metaCreateDomDocument($xml_filename);

        $result = Database::get()->querySingle("SELECT * FROM document WHERE $group_sql AND path = ?s", $metadataPath);
        if ($result) {
            Database::get()->query("UPDATE document SET
                                creator = ?s,
                                date_modified = NOW(),
                                format = ?s,
                                language = ?s
                                WHERE $group_sql AND path = ?s"
                    , ($_SESSION['givenname'] . " " . $_SESSION['surname']), $file_format, $_POST['meta_language'], $metadataPath);
        } else {
            Database::get()->query("INSERT INTO document SET
                                course_id = ?d ,
                                subsystem = ?d ,
                                subsystem_id = ?d ,
                                path = ?s,
                                filename = ?s ,
                                visible = 0,
                                creator = ?s,
                                date = ?t ,
                                date_modified = ?t ,
                                format = ?s,
                                language = ?s"
                    , $course_id, $subsystem, $subsystem_id, $metadataPath, $oldFilename
                    , ($_SESSION['givenname'] . " " . $_SESSION['surname']), $xml_date, $xml_date, $file_format, $_POST['meta_language']);
        }

        $action_message = "<p class='success'>$langMetadataMod</p>";
    }

    if (isset($_POST['replacePath']) and
            isset($_FILES['newFile']) and
            is_uploaded_file($_FILES['newFile']['tmp_name'])) {
        validateUploadedFile($_FILES['newFile']['name'], $menuTypeID);
        $replacePath = $_POST['replacePath'];
        // Check if file actually exists
        $result = Database::get()->querySingle("SELECT id, path, format FROM document WHERE
                                        $group_sql AND
                                        format <> '.dir' AND
                                        path=?s", $replacePath);
        if ($result) {
            $docId = $result->id;
            $oldpath = $result->path;
            $oldformat = $result->format;
            // check for disk quota
            $diskUsed = dir_total_space($basedir);
            if ($diskUsed - filesize($basedir . $oldpath) + $_FILES['newFile']['size'] > $diskQuotaDocument) {
                $action_message = "<p class='caution'>$langNoSpace</p>";
            } elseif (unwanted_file($_FILES['newFile']['name'])) {
                $action_message = "<p class='caution'>$langUnwantedFiletype: " .
                        q($_FILES['newFile']['name']) . "</p>";
            } else {
                $newformat = get_file_extension($_FILES['newFile']['name']);
                $newpath = preg_replace("/\\.$oldformat$/", '', $oldpath) .
                        (empty($newformat) ? '' : '.' . $newformat);
                my_delete($basedir . $oldpath);
                $affectedRows = Database::get()->query("UPDATE document SET path = ?s, format = ?s, filename = ?s, date_modified = NOW()
                          WHERE $group_sql AND path = ?s"
                                , $newpath, $newformat, ($_FILES['newFile']['name']), $oldpath)->affectedRows;
                if (!copy($_FILES['newFile']['tmp_name'], $basedir . $newpath) or $affectedRows == 0) {
                    $action_message = "<p class='caution'>$langGeneralError</p>";
                } else {
                    if (hasMetaData($oldpath, $basedir, $group_sql)) {
                        rename($basedir . $oldpath . ".xml", $basedir . $newpath . ".xml");
                        Database::get()->query("UPDATE document SET path = ?s, filename=?s WHERE $group_sql AND path = ?s"
                                , ($newpath . ".xml"), ($_FILES['newFile']['name'] . ".xml"), ($oldpath . ".xml"));
                    }
                    $didx->store($docId);
                    Log::record($course_id, MODULE_ID_DOCS, LOG_MODIFY, array('oldpath' => $oldpath,
                        'newpath' => $newpath,
                        'filename' => $_FILES['newFile']['name']));
                    $action_message = "<p class='success'>$langReplaceOK</p>";
                }
            }
        }
    }

    // Display form to add external file link
    if (isset($_GET['link'])) {
        $comment = $_GET['comment'];
        $oldComment = '';
        /*         * * Retrieve the old comment and metadata ** */
        $row = Database::get()->querySingle("SELECT * FROM document WHERE $group_sql AND path = ?s", $comment);
        if ($row) {
            $oldFilename = q($row->filename);
            $oldComment = q($row->comment);
            $oldCategory = $row->category;
            $oldTitle = q($row->title);
            $oldCreator = q($row->creator);
            $oldDate = q($row->date);
            $oldSubject = q($row->subject);
            $oldDescription = q($row->description);
            $oldAuthor = q($row->author);
            $oldLanguage = q($row->language);
            $oldCopyrighted = $row->copyrighted;

            // filsystem compability: ean gia to arxeio den yparxoun dedomena sto pedio filename
            // (ara to arxeio den exei safe_filename (=alfarithmitiko onoma)) xrhsimopoihse to
            // $fileName gia thn provolh tou onomatos arxeiou
            $fileName = my_basename($comment);
            if (empty($oldFilename))
                $oldFilename = $fileName;
            $dialogBox .= "
                        <form method='post' action='$_SERVER[SCRIPT_NAME]?course=$course_code'>
                        <fieldset>
                          <legend>$langAddComment</legend>
                          <input type='hidden' name='commentPath' value='" . q($comment) . "' />
                          <input type='hidden' size='80' name='file_filename' value='$oldFilename' />
                          $group_hidden_input
                          <table class='tbl' width='100%'>
                          <tr>
                            <th>$langWorkFile:</th>
                            <td>$oldFilename</td>
                          </tr>
                          <tr>
                            <th>$langTitle:</th>
                            <td><input type='text' size='60' name='file_title' value='$oldTitle' /></td>
                          </tr>
                          <tr>
                            <th>$langComment:</th>
                            <td><input type='text' size='60' name='file_comment' value='$oldComment' /></td>
                          </tr>
                          <tr>
                            <th>$langCategory:</th>
                            <td>" .
                    selection(array('0' => $langCategoryOther,
                        '1' => $langCategoryExcercise,
                        '2' => $langCategoryLecture,
                        '3' => $langCategoryEssay,
                        '4' => $langCategoryDescription,
                        '5' => $langCategoryExample,
                        '6' => $langCategoryTheory), 'file_category', $oldCategory) . "</td>
                          </tr>
                          <tr>
                            <th>$langSubject : </th>
                            <td><input type='text' size='60' name='file_subject' value='$oldSubject' /></td>
                          </tr>
                          <tr>
                            <th>$langDescription : </th>
                            <td><input type='text' size='60' name='file_description' value='$oldDescription' /></td>
                          </tr>
                          <tr>
                            <th>$langAuthor : </th>
                            <td><input type='text' size='60' name='file_author' value='$oldAuthor' /></td>
                          </tr>";
            $dialogBox .= "<tr><th>$langCopyrighted : </th><td>";
            $dialogBox .= selection($copyright_titles, 'file_copyrighted', $oldCopyrighted) . "</td></tr>";

            // display combo box for language selection
            $dialogBox .= "
                                <tr>
                                <th>$langLanguage :</th>
                                <td>" .
                    selection(array('en' => $langEnglish,
                        'fr' => $langFrench,
                        'de' => $langGerman,
                        'el' => $langGreek,
                        'it' => $langItalian,
                        'es' => $langSpanish), 'file_language', $oldLanguage) .
                    "</td>
                        </tr>
                        <tr>
                        <th>&nbsp;</th>
                        <td class='right'><input type='submit' value='$langOkComment' /></td>
                        </tr>
                        <tr>
                        <th>&nbsp;</th>
                        <td class='right'>$langNotRequired</td>
                        </tr>
                        </table>
                        <input type='hidden' size='80' name='file_creator' value='$oldCreator' />
                        <input type='hidden' size='80' name='file_date' value='$oldDate' />
                        <input type='hidden' size='80' name='file_oldLanguage' value='$oldLanguage' />
                        </fieldset>
                        </form>
                        \n\n";
        } else {
            $action_message = "<p class='caution'>$langFileNotFound</p>";
        }
    }

    // Display form to replace/overwrite an existing file
    if (isset($_GET['replace'])) {
        $result = Database::get()->querySingle("SELECT filename FROM document
                                        WHERE $group_sql AND
                                                format <> '.dir' AND
                                                path = ?s", $_GET['replace']);
        if ($result) {
            $filename = q($result->filename);
            $replacemessage = sprintf($langReplaceFile, '<b>' . $filename . '</b>');
            $dialogBox = "
                                <form method='post' action='$_SERVER[SCRIPT_NAME]?course=$course_code' enctype='multipart/form-data'>
                                <fieldset>
                                <input type='hidden' name='replacePath' value='" . q($_GET['replace']) . "' />
                                $group_hidden_input
                                        <table class='tbl' width='100%'>
                                        <tr>
                                                <td>$replacemessage</td>
                                                <td><input type='file' name='newFile' size='35' /></td>
                                                <td><input type='submit' value='$langReplace' /></td>
                                        </tr>
                                        </table>
                                </fieldset>
                                </form>
                                <br />\n";
        }
    }

    // Emfanish ths formas gia tropopoihsh comment
    if (isset($_GET['comment'])) {
        $comment = $_GET['comment'];
        $oldComment = '';
        /*         * * Retrieve the old comment and metadata ** */
        $row = Database::get()->querySingle("SELECT * FROM document WHERE $group_sql AND path = ?s", $comment);
        if ($row) {
            $oldFilename = q($row->filename);
            $oldComment = q($row->comment);
            $oldCategory = $row->category;
            $oldTitle = q($row->title);
            $oldCreator = q($row->creator);
            $oldDate = q($row->date);
            $oldSubject = q($row->subject);
            $oldDescription = q($row->description);
            $oldAuthor = q($row->author);
            $oldLanguage = q($row->language);
            $oldCopyrighted = $row->copyrighted;

            // filsystem compability: ean gia to arxeio den yparxoun dedomena sto pedio filename
            // (ara to arxeio den exei safe_filename (=alfarithmitiko onoma)) xrhsimopoihse to
            // $fileName gia thn provolh tou onomatos arxeiou
            $fileName = my_basename($comment);
            if (empty($oldFilename))
                $oldFilename = $fileName;
            $dialogBox .= "
                        <form method='post' action='$_SERVER[SCRIPT_NAME]?course=$course_code'>
                        <fieldset>
                          <legend>$langAddComment</legend>
                          <input type='hidden' name='commentPath' value='" . q($comment) . "' />
                          <input type='hidden' size='80' name='file_filename' value='$oldFilename' />
                          $group_hidden_input
                          <table class='tbl' width='100%'>
                          <tr>
                            <th>$langWorkFile:</th>
                            <td>$oldFilename</td>
                          </tr>
                          <tr>
                            <th>$langTitle:</th>
                            <td><input type='text' size='60' name='file_title' value='$oldTitle' /></td>
                          </tr>
                          <tr>
                            <th>$langComment:</th>
                            <td><input type='text' size='60' name='file_comment' value='$oldComment' /></td>
                          </tr>
                          <tr>
                            <th>$langCategory:</th>
                            <td>" .
                    selection(array('0' => $langCategoryOther,
                        '1' => $langCategoryExcercise,
                        '2' => $langCategoryLecture,
                        '3' => $langCategoryEssay,
                        '4' => $langCategoryDescription,
                        '5' => $langCategoryExample,
                        '6' => $langCategoryTheory), 'file_category', $oldCategory) . "</td>
                          </tr>
                          <tr>
                            <th>$langSubject : </th>
                            <td><input type='text' size='60' name='file_subject' value='$oldSubject' /></td>
                          </tr>
                          <tr>
                            <th>$langDescription : </th>
                            <td><input type='text' size='60' name='file_description' value='$oldDescription' /></td>
                          </tr>
                          <tr>
                            <th>$langAuthor : </th>
                            <td><input type='text' size='60' name='file_author' value='$oldAuthor' /></td>
                          </tr>";
            $dialogBox .= "<tr><th>$langCopyrighted : </th><td>";
            $dialogBox .= selection($copyright_titles, 'file_copyrighted', $oldCopyrighted) . "</td></tr>";

            // display combo box for language selection
            $dialogBox .= "
                                <tr>
                                <th>$langLanguage :</th>
                                <td>" .
                    selection(array('en' => $langEnglish,
                        'fr' => $langFrench,
                        'de' => $langGerman,
                        'el' => $langGreek,
                        'it' => $langItalian,
                        'es' => $langSpanish), 'file_language', $oldLanguage) .
                    "</td>
                        </tr>
                        <tr>
                        <th>&nbsp;</th>
                        <td class='right'><input type='submit' value='$langOkComment' /></td>
                        </tr>
                        <tr>
                        <th>&nbsp;</th>
                        <td class='right'>$langNotRequired</td>
                        </tr>
                        </table>
                        <input type='hidden' size='80' name='file_creator' value='$oldCreator' />
                        <input type='hidden' size='80' name='file_date' value='$oldDate' />
                        <input type='hidden' size='80' name='file_oldLanguage' value='$oldLanguage' />
                        </fieldset>
                        </form>
                        \n\n";
        } else {
            $action_message = "<p class='caution'>$langFileNotFound</p>";
        }
    }

    // Emfanish ths formas gia tropopoihsh metadata
    if (isset($_GET['metadata'])) {

        $metadata = $_GET['metadata'];
        $row = Database::get()->querySingle("SELECT filename FROM document WHERE $group_sql AND path = ?s", $metadata);
        if ($row) {
            $oldFilename = q($row->filename);

            // filesystem compability: ean gia to arxeio den yparxoun dedomena sto pedio filename
            // (ara to arxeio den exei safe_filename (=alfarithmitiko onoma)) xrhsimopoihse to
            // $fileName gia thn provolh tou onomatos arxeiou
            $fileName = my_basename($metadata);
            if (empty($oldFilename))
                $oldFilename = $fileName;
            $real_filename = $basedir . str_replace('/..', '', q($metadata));

            $dialogBox .= metaCreateForm($metadata, $oldFilename, $real_filename);
        } else {
            $action_message = "<p class='caution'>$langFileNotFound</p>";
        }
    }

    // Visibility commands
    if (isset($_GET['mkVisibl']) || isset($_GET['mkInvisibl'])) {
        if (isset($_GET['mkVisibl'])) {
            $newVisibilityStatus = 1;
            $visibilityPath = $_GET['mkVisibl'];
        } else {
            $newVisibilityStatus = 0;
            $visibilityPath = $_GET['mkInvisibl'];
        }
        Database::get()->query("UPDATE document SET visible=?d
                                          WHERE $group_sql AND
                                                path = ?s", $newVisibilityStatus, $visibilityPath);
        $r = Database::get()->querySingle("SELECT id FROM document WHERE $group_sql AND path = ?s", $visibilityPath);
        $didx->store($r->id);
        $action_message = "<p class='success'>$langViMod</p>";
    }

    // Public accessibility commands
    if (isset($_GET['public']) || isset($_GET['limited'])) {
        $new_public_status = intval(isset($_GET['public']));
        $path = isset($_GET['public']) ? $_GET['public'] : $_GET['limited'];
        Database::get()->query("UPDATE document SET public = ?d
                                          WHERE $group_sql AND
                                                path = ?s", $new_public_status, $path);
        $r = Database::get()->querySingle("SELECT id FROM document WHERE $group_sql AND path = ?s", $path);
        $didx->store($r->id);
        $action_message = "<p class='success'>$langViMod</p>";
    }
} // teacher only
// Common for teachers and students
// define current directory
// Check if $var is set and return it - if $is_file, then return only dirname part

function pathvar(&$var, $is_file = false) {
    static $found = false;
    if ($found) {
        return '';
    }
    if (isset($var)) {
        $found = true;
        $var = str_replace('..', '', $var);
        if ($is_file) {
            return dirname($var);
        } else {
            return $var;
        }
    }
    return '';
}

$curDirPath = pathvar($_GET['openDir'], false) .
        pathvar($_GET['createDir'], false) .
        pathvar($_POST['moveTo'], false) .
        pathvar($_POST['newDirPath'], false) .
        pathvar($_POST['uploadPath'], false) .
        pathvar($_POST['filePath'], true) .
        pathvar($_GET['move'], true) .
        pathvar($_GET['rename'], true) .
        pathvar($_GET['replace'], true) .
        pathvar($_GET['comment'], true) .
        pathvar($_GET['metadata'], true) .
        pathvar($_GET['mkInvisibl'], true) .
        pathvar($_GET['mkVisibl'], true) .
        pathvar($_GET['public'], true) .
        pathvar($_GET['limited'], true) .
        pathvar($_POST['sourceFile'], true) .
        pathvar($_POST['replacePath'], true) .
        pathvar($_POST['commentPath'], true) .
        pathvar($_POST['metadataPath'], true);

if ($curDirPath == '/' or $curDirPath == '\\') {
    $curDirPath = '';
}
$curDirName = my_basename($curDirPath);
$parentDir = dirname($curDirPath);
if ($parentDir == '\\') {
    $parentDir = '/';
}

if (strpos($curDirName, '/../') !== false or ! is_dir(realpath($basedir . $curDirPath))) {
    $tool_content .= $langInvalidDir;
    draw($tool_content, $menuTypeID);
    exit;
}

$order = 'ORDER BY filename';
$sort = 'name';
$reverse = false;
if (isset($_GET['sort'])) {
    if ($_GET['sort'] == 'type') {
        $order = 'ORDER BY format';
        $sort = 'type';
    } elseif ($_GET['sort'] == 'date') {
        $order = 'ORDER BY date_modified';
        $sort = 'date';
    }
}
if (isset($_GET['rev'])) {
    $order .= ' DESC';
    $reverse = true;
}

list($filter, $compatiblePlugin) = (isset($_REQUEST['docsfilter'])) ? select_proper_filters($_REQUEST['docsfilter']) : array('', true);

/* * * Retrieve file info for current directory from database and disk ** */
$result = Database::get()->queryArray("SELECT * FROM document
                        WHERE $group_sql AND
                                path LIKE '$curDirPath/%' AND
                                path NOT LIKE '$curDirPath/%/%' $filter $order");

$fileinfo = array();
foreach ($result as $row) {
    if ($real_path = common_doc_path($row->extra_path, true)) {
        // common docs
        $path = $real_path;
    } else {
        $path = $basedir . $row->path;
    }
    if (!$real_path and $row->extra_path) {
        // external file
        $size = 0;
    } else {
        $size = filesize($path);
    }
    $fileinfo[] = array(
        'is_dir' => ($row->format == '.dir'),
        'size' => $size,
        'title' => $row->title,
        'filename' => $row->filename,
        'format' => $row->format,
        'path' => $row->path,
        'extra_path' => $row->extra_path,
        'visible' => ($row->visible == 1),
        'public' => $row->public,
        'comment' => $row->comment,
        'copyrighted' => $row->copyrighted,
        'date' => $row->date_modified,
        'object' => MediaResourceFactory::initFromDocument($row));
}
// end of common to teachers and students
// ----------------------------------------------
// Display
// ----------------------------------------------

$cmdCurDirPath = rawurlencode($curDirPath);
$cmdParentDir = rawurlencode($parentDir);

if ($can_upload) {
    // Action result message
    if (!empty($action_message)) {
        $tool_content .= $action_message;
    }
    // available actions
    if (!$is_in_tinymce) {
        $diskQuotaDocument = $diskQuotaDocument * 1024 / 1024;
        $tool_content .= "<div id='operations_container'>
                    <ul id='opslist'>
                       <li><a href='upload.php?course=$course_code&amp;{$groupset}uploadPath=$curDirPath'>$langDownloadFile</a></li>
                       <li><a href='{$base_url}createDir=$cmdCurDirPath'>$langCreateDir</a></li>
                       <li><a href='upload.php?course=$course_code&amp;{$groupset}uploadPath=$curDirPath&amp;ext=true'>$langExternalFile</a></li>";
        if (!defined('COMMON_DOCUMENTS') and get_config('enable_common_docs')) {
            $tool_content .= "<li><a href='../units/insert.php?course=$course_code&amp;dir=$curDirPath&amp;type=doc&amp;id=-1'>$langCommonDocs</a>";
        }
        $tool_content .= "<li><a href='{$base_url}showQuota=true'>$langQuotaBar</a></li>
            </ul></div>";
    }

    // Dialog Box
    if (!empty($dialogBox)) {
        $tool_content .= $dialogBox;
    }
}

// check if there are documents
$doc_count = Database::get()->querySingle("SELECT COUNT(*) as count FROM document WHERE $group_sql $filter" .
                ($can_upload ? '' : " AND visible=1"))->count;
if ($doc_count == 0) {
    $tool_content .= "<p class='alert1'>$langNoDocuments</p>";
} else {
    // Current Directory Line
    $tool_content .= "<table width='100%' class='tbl'>";

    if ($can_upload) {
        $cols = 4;
    } else {
        $cols = 3;
    }

    $download_path = empty($curDirPath) ? '/' : $curDirPath;
    $download_dir = ($is_in_tinymce) ? '' : "<a href='{$base_url}download=$download_path'><img src='$themeimg/save_s.png' width='16' height='16' align='middle' alt='$langDownloadDir' title='$langDownloadDir'></a>";
    $tool_content .= "<tr>
        <td colspan='$cols'><div class='sub_title1'><b>$langDirectory:</b> " . make_clickable_path($curDirPath) .
            "&nbsp;$download_dir<br></div></td>
        <td><div align='right'>";

    // Link for sortable table headings
    function headlink($label, $this_sort) {
        global $sort, $reverse, $curDirPath, $base_url, $themeimg, $langUp, $langDown;

        if (empty($curDirPath)) {
            $path = '/';
        } else {
            $path = $curDirPath;
        }
        if ($sort == $this_sort) {
            $this_reverse = !$reverse;
            $indicator = " <img src='$themeimg/arrow_" .
                    ($reverse ? 'up' : 'down') . ".png' alt='" .
                    ($reverse ? $langUp : $langDown) . "'>";
        } else {
            $this_reverse = $reverse;
            $indicator = '';
        }
        return '<a href="' . $base_url . 'openDir=' . $path .
                '&amp;sort=' . $this_sort . ($this_reverse ? '&amp;rev=1' : '') .
                '">' . $label . $indicator . '</a>';
    }

    /*     * * go to parent directory ** */
    if ($curDirName) { // if the $curDirName is empty, we're in the root point and we can't go to a parent dir
        $parentlink = $base_url . 'openDir=' . $cmdParentDir;
        $tool_content .= "<a href='$parentlink'>$langUp</a> <a href='$parentlink'><img src='$themeimg/folder_up.png' height='16' width='16' alt='$langUp'/></a>";
    }
    $tool_content .= "</div></td>
    </tr>
    </table>
    <table width='100%' class='tbl_alt'>
    <tr>";
    $tool_content .= "<th width='50' class='center'><b>" . headlink($langType, 'type') . '</b></th>' .
            "<th><div align='left'>" . headlink($langName, 'name') . '</div></th>' .
            "<th width='60' class='center'><b>$langSize</b></th>" .
            "<th width='80' class='center'><b>" . headlink($langDate, 'date') . '</b></th>';
    if (!$is_in_tinymce) {
        $tool_content .= "<th width='50' class='center'><b>$langCommands</b></th>";
    }
    $tool_content .= "\n    </tr>";

    // -------------------------------------
    // Display directories first, then files
    // -------------------------------------
    $counter = 0;
    foreach (array(true, false) as $is_dir) {
        foreach ($fileinfo as $entry) {
            $link_title_extra = '';
            if (($entry['is_dir'] != $is_dir) or ( !$can_upload and ( !resource_access($entry['visible'], $entry['public'])))) {
                continue;
            }
            $cmdDirName = $entry['path'];
            if ($entry['visible']) {
                if ($counter % 2 == 0) {
                    $style = 'class="even"';
                } else {
                    $style = 'class="odd"';
                }
            } else {
                $style = ' class="invisible"';
            }
            if ($is_dir) {
                $img_href = icon('folder');
                $file_url = $base_url . "openDir=$cmdDirName";
                $link_title = q($entry['filename']);
                $dload_msg = $langDownloadDir;
                $link_href = "<a href='$file_url'>$link_title</a>";
            } else {
                $img_href = icon(choose_image('.' . $entry['format']));
                $file_url = file_url($cmdDirName, $entry['filename']);
                if ($entry['extra_path']) {
                    $cdpath = common_doc_path($entry['extra_path']);
                    if ($cdpath) {
                        if ($is_editor) {
                            $link_title_extra .= '&nbsp;' .
                                    $common_doc_visible ? 'common' : 'common_invisible';
                        }
                    } else {
                        // External file URL
                        $file_url = $entry['extra_path'];
                        if ($is_editor) {
                            $link_title_extra .= '&nbsp;external';
                        }
                    }
                }
                if ($copyid = $entry['copyrighted'] and
                        $copyicon = $copyright_icons[$copyid]) {
                    $link_title_extra .= "&nbsp;" .
                            icon($copyicon, $copyright_titles[$copyid], $copyright_links[$copyid], null, 'png', 'target="_blank"');
                }
                $dload_msg = $langSave;

                $dObj = $entry['object'];
                $dObj->setAccessURL($file_url);
                $dObj->setPlayURL(file_playurl($cmdDirName, $entry['filename']));
                if ($is_in_tinymce && !$compatiblePlugin) // use Access/DL URL for non-modable tinymce plugins
                    $dObj->setPlayURL($dObj->getAccessURL());

                $link_href = MultimediaHelper::chooseMediaAhref($dObj);
            }
            if (!$entry['extra_path'] or common_doc_path($entry['extra_path'])) {
                // Normal or common document
                $download_url = $base_url . "download=$cmdDirName";
            } else {
                // External document
                $download_url = $entry['extra_path'];
            }
            $download_icon = icon('save_s', $dload_msg, $download_url);
            $tool_content .= "<tr $style>
                                               <td class='center' valign='top'>$img_href</td>
                                               <td>$link_href $link_title_extra";

            /*             * * comments ** */
            if (!empty($entry['comment'])) {
                $tool_content .= "<br /><span class='comment'>" .
                        nl2br(htmlspecialchars($entry['comment'])) .
                        "</span>";
            }
            $tool_content .= "</td>";
            $padding = '&nbsp;';
            $padding2 = '';
            $date = nice_format($entry['date'], true, true);
            $date_with_time = nice_format($entry['date'], true);
            if ($is_dir) {
                $tool_content .= "\n<td>&nbsp;</td>\n<td class='center'>$date</td>";
                $padding = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            } else if ($entry['format'] == ".meta") {
                $size = format_file_size($entry['size']);
                $tool_content .= "\n<td class='center'>$size</td>\n<td class='center'>$date</td>";
                $padding = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                $padding2 = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            } else {
                $size = format_file_size($entry['size']);
                $tool_content .= "\n<td class='center'>$size</td>\n<td class='center' title='$date_with_time'>$date</td>";
            }
            if (!$is_in_tinymce) {
                if ($can_upload) {
                    $tool_content .= "\n<td class='right toolbox' valign='top'><form action='$_SERVER[SCRIPT_NAME]?course=$course_code' method='post'>" . $group_hidden_input .
                            "<input type='hidden' name='filePath' value='$cmdDirName' />" .
                            $download_icon . $padding;
                    if (!$is_dir && $entry['format'] != ".meta") {
                        /*                         * * replace/overwrite command, only applies to files ** */
                        $tool_content .= "<a href='{$base_url}replace=$cmdDirName'>" .
                                "<img src='$themeimg/replace.png' " .
                                "title='$langReplace' alt='$langReplace' /></a>&nbsp;";
                    }
                    /*                     * * delete command ** */
                    $tool_content .= "<input type='image' src='$themeimg/delete.png' alt='$langDelete' title='$langDelete' name='delete' value='1' onClick=\"return confirmation('" . js_escape($langConfirmDelete . ' ' . $entry['filename']) . "');\" />&nbsp;" . $padding2;
                    if ($entry['format'] != '.meta') {
                        $tool_content .= icon('move', $langMove, "{$base_url}move=$cmdDirName") .
                                "&nbsp;" . icon('rename', $langRename, "{$base_url}rename=$cmdDirName") .
                                "&nbsp;" . icon('comment_edit', $langComment, "{$base_url}comment=$cmdDirName") .
                                "&nbsp;";
                    }
                    /*                     * * metadata command ** */
                    if (get_config("insert_xml_metadata")) {
                        $xmlCmdDirName = ($entry['format'] == ".meta" && get_file_extension($cmdDirName) == "xml") ? substr($cmdDirName, 0, -4) : $cmdDirName;
                        $tool_content .= icon('lom', $langMetadata, "{$base_url}metadata=$xmlCmdDirName") .
                                "&nbsp;";
                    }
                    if ($entry['visible']) {
                        $tool_content .= icon('visible', $langVisible, "{$base_url}mkInvisibl=$cmdDirName");
                    } else {
                        $tool_content .= icon('invisible', $langVisible, "{$base_url}mkVisibl=$cmdDirName");
                    }
                    $tool_content .= "&nbsp;";
                    // For common docs, $course_id = -1 - disable public icon there
                    if ($course_id > 0 and course_status($course_id) == COURSE_OPEN) {
                        if ($entry['public']) {
                            $tool_content .= icon('access_public', $langResourceAccess, "{$base_url}limited=$cmdDirName");
                        } else {
                            $tool_content .= icon('access_limited', $langResourceAccess, "{$base_url}public=$cmdDirName");
                        }
                        $tool_content .= "&nbsp;";
                    }
                    if ($subsystem == GROUP and isset($is_member) and ( $is_member)) {
                        $tool_content .= "<a href='{$urlAppend}modules/work/group_work.php?course=$course_code" .
                                "&amp;group_id=$group_id&amp;submit=$cmdDirName'>" .
                                "<img src='$themeimg/book.png' " .
                                "title='$langGroupSubmit' alt='$langGroupSubmit' /></a>";
                    }
                    $tool_content .= "</form></td>";
                    $tool_content .= "</tr>\n";
                } else { // only for students
                    $tool_content .= "<td>$download_icon</td>";
                }
            }
            $counter++;
        }
    }
    $tool_content .= "\n    </table>\n";
    if ($can_upload && !$is_in_tinymce) {
        $tool_content .= "\n    <br><div class='right smaller'>$langMaxFileSize " . ini_get('upload_max_filesize') . "</div>\n";
    }
    $tool_content .= "\n    <br />";
}
if (defined('SAVED_COURSE_CODE')) {
    $course_code = SAVED_COURSE_CODE;
    $course_id = SAVED_COURSE_ID;
}
add_units_navigation(TRUE);
draw($tool_content, $menuTypeID, null, $head_content);

function select_proper_filters($requestDocsFilter) {
    $filter = '';
    $compatiblePlugin = true;

    switch ($requestDocsFilter) {
        case 'image':
            $ors = '';
            foreach (MultimediaHelper::getSupportedImages() as $imgfmt)
                $ors .= " OR format LIKE '$imgfmt'";
            $filter = "AND (format LIKE '.dir' $ors)";
            break;
        case 'eclmedia':
            $ors = '';
            foreach (MultimediaHelper::getSupportedMedia() as $mediafmt)
                $ors .= " OR format LIKE '$mediafmt'";
            $filter = "AND (format LIKE '.dir' $ors)";
            break;
        case 'media':
            $compatiblePlugin = false;
            $ors = '';
            foreach (MultimediaHelper::getSupportedMedia() as $mediafmt)
                $ors .= " OR format LIKE '$mediafmt'";
            $filter = "AND (format LIKE '.dir' $ors)";
            break;
        case 'zip':
            $filter = "AND (format LIKE '.dir' OR FORMAT LIKE 'zip')";
            break;
        case 'file':
            $filter = '';
            break;
        default:
            break;
    }

    return array($filter, $compatiblePlugin);
}
