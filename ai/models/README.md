# AI Models Directory

This folder holds model weights for the VEHISAFE AI service.

## Current Models

| File | Purpose | Source | Size |
|------|---------|--------|------|
| `yolov8n.pt` | COCO-pretrained object detector (auto-downloaded on first run) | [Ultralytics](https://github.com/ultralytics/ultralytics) | ~6 MB |

## Adding a Custom-Trained Seatbelt Model

If you (or a future developer) train a custom seatbelt model:

1. Train with YOLOv8 using labeled seatbelt images:
   ```python
   from ultralytics import YOLO
   model = YOLO('yolov8n.pt')
   model.train(data='seatbelt_dataset.yaml', epochs=100, imgsz=640)