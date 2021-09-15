<?php declare(strict_types=1);
/**
 * LICENSE
 *
 * This software and its source code is protected by copyright law (Sec. 69a ff. UrhG).
 * It is not allowed to make any kinds of modifications, nor must it be copied,
 * or published without explicit permission. Misuse will lead to persecution.
 *
 * @copyright  2021 infomax websolutions GmbH
 * @link       http://www.infomax-it.de
 */

namespace de\imxnet\neos\imxplatform\import\Command;

use de\imxnet\neos\imxplatform\import\Import;
use Neos\Flow\Cli\CommandController;

final class PlatformImportCommandController extends CommandController {

  private Import $import;

  /**
   *
   * @param Import $import
   */
  public function __construct(Import $import) {
    parent::__construct();
    $this->import = $import;
  }

  /**
   * Das ist ein test
   */
  public function importCommand() {
    $this->import->import();
  }
}
