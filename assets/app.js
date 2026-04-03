import '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';

import FileUploadController from './controllers/file_upload_controller.js';
import ImportWizardController from './controllers/import_wizard_controller.js';
import YearSelectorController from './controllers/year_selector_controller.js';
import TableSortController from './controllers/table_sort_controller.js';
import FilterController from './controllers/filter_controller.js';

const app = Application.start();
app.register('file-upload', FileUploadController);
app.register('import-wizard', ImportWizardController);
app.register('year-selector', YearSelectorController);
app.register('table-sort', TableSortController);
app.register('filter', FilterController);
