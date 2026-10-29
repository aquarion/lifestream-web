// Leaflet with the heatmap plugin. leaflet.heat registers itself on a global
// `L`, so expose Leaflet globally before it loads (this module is imported
// first, and ES modules evaluate in import order).

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.L = L;

export default L;
