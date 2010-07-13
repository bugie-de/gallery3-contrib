<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2010 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class Html_Uploader_Controller extends Controller {
  public function app($id) {
    $album = ORM::factory("item", $id);
    access::required("view", $album);
    access::required("add", $album);
    if (!$album->is_album()) {
      $album = $album->parent();
    }

    print $this->_get_add_form($album);
  }

  public function add($id) {
    $album = ORM::factory("item", $id);
    access::required("view", $album);
    access::required("add", $album);
    access::verify_csrf();

    $form = $this->_get_add_form($album);
    if ($form->validate()) {
      batch::start();

      foreach (array("file1", "file2", "file3") as $key) {
        if ($form->add_photos->$key->value == "") {
          continue;
        }

        try {
          $temp_filename = $form->add_photos->$key->value;
          $item = ORM::factory("item");
          $item->name = basename($temp_filename);
          $item->title = item::convert_filename_to_title($item->name);
          $item->parent_id = $album->id;
          $item->set_data_file($temp_filename);

          $path_info = @pathinfo($temp_filename);
          if (array_key_exists("extension", $path_info) &&
              in_array(strtolower($path_info["extension"]), array("flv", "mp4", "m4v"))) {
            $item->type = "movie";
            $item->save();
            log::success("content", t("Added a movie"),
                         html::anchor("movies/$item->id", t("view movie")));
          } else {
            $item->type = "photo";
            $item->save();
            log::success("content", t("Added a photo"),
                         html::anchor("photos/$item->id", t("view photo")));
          }
          module::event("add_photos_form_completed", $item, $form);
          
        } catch (Exception $e) {
          // Lame error handling for now.  Just record the exception and move on
          Kohana_Log::add("error", $e->getMessage() . "\n" . $e->getTraceAsString());

          // Ugh.  I hate to use instanceof, But this beats catching the exception separately since
          // we mostly want to treat it the same way as all other exceptions
          if ($e instanceof ORM_Validation_Exception) {
            Kohana_Log::add("error", "Validation errors: " . print_r($e->validation->errors(), 1));
          }
        }

        if (file_exists($temp_filename)) {
          unlink($temp_filename);
        }
      }
      batch::stop();
      print json_encode(array("result" => "success"));
    } else {
      print json_encode(array("result" => "error", "form" => (string) $form));
    }
  }

  private function _get_add_form($album) {
    $form = new Forge("html_uploader/add/{$album->id}", "", "post", array("id" => "g-add-photos-form"));
    $group = $form->group("add_photos")
      ->label(t("Add photos to %album_title", array("album_title" => html::purify($album->title))));
    $group->upload("file1")->add_rule("foo");
    $group->upload("file2");
    $group->upload("file3");

    module::event("add_photos_form", $album, $form);

    $group = $form->group("buttons")->label("");
    $group->submit("")->value(t("Upload"));

    return $form;
  }
}
