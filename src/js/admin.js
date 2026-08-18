// Admin context entry module: wiring only.
import { CopyButton } from './ui/CopyButton.js';
import { DeleteConfirmation } from './ui/DeleteConfirmation.js';
import { TrackingCodeGenerator } from './ui/TrackingCodeGenerator.js';
import { TrackingCodeClient } from './services/TrackingCodeClient.js';

CopyButton.initAll();
DeleteConfirmation.initAll();
TrackingCodeGenerator.initAll( TrackingCodeClient.fromPage() );
