"""
============================================================
VEHISAFE AI SERVICE - BLACK-SPOT RISK MODEL TRAINING
============================================================
Trains a Random Forest Regressor on synthetic accident data
to predict risk_score (0.0-1.0) for any GPS coordinate.

Features: latitude, longitude, road_condition, weather,
          traffic_density, accident_count

Usage:
    python blackspot/train.py
============================================================
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import pandas as pd
import numpy as np
import joblib
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

from config import Config
from utils.logger import get_logger


def main():
    logger = get_logger("blackspot.train")
    logger.info("=== Black-Spot Model Training ===")

    # ---- Paths ----
    dataset_path = Config.BASE_DIR / "blackspot" / "datasets" / "demo_accidents.csv"
    model_path   = Config.BLACKSPOT_MODEL_PATH
    encoders_path = model_path.parent / "encoders.joblib"

    if not dataset_path.exists():
        logger.error("Dataset not found: %s", dataset_path)
        logger.error("Run the PowerShell block to create it first.")
        return 1

    # ---- Load ----
    logger.info("Loading: %s", dataset_path)
    df = pd.read_csv(dataset_path)
    logger.info("Loaded %d records", len(df))

    # ---- Encode categoricals ----
    cat_cols = ["road_condition", "weather", "traffic_density"]
    encoders = {}
    for col in cat_cols:
        le = LabelEncoder()
        df[col + "_enc"] = le.fit_transform(df[col])
        encoders[col] = le
        logger.info("%s classes: %s", col, list(le.classes_))

    # ---- Features / Target ----
    feature_cols = ["latitude", "longitude",
                    "road_condition_enc", "weather_enc",
                    "traffic_density_enc", "accident_count"]
    X = df[feature_cols].values
    y = df["risk_score"].values

    logger.info("Features: %s", feature_cols)
    logger.info("Training shape: X=%s, y=%s", X.shape, y.shape)

    # ---- Split ----
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42
    )
    logger.info("Split: %d train, %d test", len(X_train), len(X_test))

    # ---- Train ----
    model = RandomForestRegressor(
        n_estimators=100,
        max_depth=8,
        min_samples_leaf=2,
        random_state=42,
        n_jobs=-1,
    )
    logger.info("Training RandomForestRegressor...")
    model.fit(X_train, y_train)
    logger.info("Training complete.")

    # ---- Evaluate ----
    y_pred = model.predict(X_test)
    mae  = mean_absolute_error(y_test, y_pred)
    rmse = np.sqrt(mean_squared_error(y_test, y_pred))
    r2   = r2_score(y_test, y_pred)

    logger.info("=== Evaluation ===")
    logger.info("MAE:  %.4f", mae)
    logger.info("RMSE: %.4f", rmse)
    logger.info("R²:   %.4f", r2)

    # ---- Feature importance ----
    logger.info("=== Feature Importance ===")
    importances = model.feature_importances_
    for name, imp in sorted(zip(feature_cols, importances), key=lambda x: -x[1]):
        logger.info("  %-22s %.4f", name, imp)

    # ---- Save ----
    model_path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(model, model_path)
    joblib.dump(encoders, encoders_path)

    logger.info("Saved model:    %s (%d bytes)",
                model_path, model_path.stat().st_size)
    logger.info("Saved encoders: %s", encoders_path)
    logger.info("=== Done ===")
    return 0


if __name__ == "__main__":
    sys.exit(main())
