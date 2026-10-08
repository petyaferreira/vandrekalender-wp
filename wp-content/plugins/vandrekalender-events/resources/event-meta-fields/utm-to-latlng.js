/**
 * Convert EPSG:25832 (ETRS89 / UTM zone 32N) coordinates, as returned by
 * Adressevælger, to latitude/longitude.
 *
 * Our own helper instead of the proj4 library: we need exactly one
 * conversion, and proj4 grew the editor script from 13 KiB to 147 KiB.
 * The server-side geocoder gets the same formula, ported line for line to
 * PHP, in PR 2 of docs/dawa-migration-plan.md, so the editor and the
 * scrapers always agree. Keep the two in sync.
 *
 * Inverse transverse Mercator with Krüger's series on the GRS80 ellipsoid.
 * Checked against proj4 for addresses across Denmark (Bornholm included),
 * within 1 cm. ETRS89 and WGS84 differ by under a metre, so the result is
 * used as WGS84 directly.
 */

const A = 6378137; // GRS80 semi-major axis, metres.
const F = 1 / 298.257222101; // GRS80 flattening.
const K0 = 0.9996; // UTM scale factor on the central meridian.
const LON0 = 9; // Central meridian of zone 32, degrees.
const FALSE_EASTING = 500000;

const n = F / (2 - F);
const n2 = n * n;
const n3 = n2 * n;
const RECTIFYING_RADIUS = (A / (1 + n)) * (1 + n2 / 4 + (n2 * n2) / 64);
const BETA = [
  n / 2 - (2 / 3) * n2 + (37 / 96) * n3,
  n2 / 48 + n3 / 15,
  (17 / 480) * n3,
];
const DELTA = [
  2 * n - (2 / 3) * n2 - 2 * n3,
  (7 / 3) * n2 - (8 / 5) * n3,
  (56 / 15) * n3,
];

const toDegrees = rad => (rad * 180) / Math.PI;

// Rounded to 7 decimals (about 1 cm) so the stored values stay readable.
const round7 = value => Math.round(value * 1e7) / 1e7;

/**
 * @param {number} x Easting in metres.
 * @param {number} y Northing in metres.
 * @return {{lat: number, lng: number}} Latitude/longitude in degrees.
 */
export const utmToLatLng = (x, y) => {
  const xi = y / (K0 * RECTIFYING_RADIUS);
  const eta = (x - FALSE_EASTING) / (K0 * RECTIFYING_RADIUS);

  let xiPrime = xi;
  let etaPrime = eta;
  BETA.forEach((beta, i) => {
    const j = 2 * (i + 1);
    xiPrime -= beta * Math.sin(j * xi) * Math.cosh(j * eta);
    etaPrime -= beta * Math.cos(j * xi) * Math.sinh(j * eta);
  });

  // Conformal latitude, then the geodetic latitude from it.
  const chi = Math.asin(Math.sin(xiPrime) / Math.cosh(etaPrime));
  let lat = chi;
  DELTA.forEach((delta, i) => {
    lat += delta * Math.sin(2 * (i + 1) * chi);
  });

  const lng =
    LON0 + toDegrees(Math.atan2(Math.sinh(etaPrime), Math.cos(xiPrime)));

  return { lat: round7(toDegrees(lat)), lng: round7(lng) };
};
