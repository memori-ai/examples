import requests
import json

res = requests.post(
    "https://backend.memori.ai/memori/v2/session",
    json={"memoriId": "9b0a2913-d3d8-4e98-a49d-6e1c99479e1b"},
    headers={"Content-Type": "application/json"}
)

response = res.json()

if response['resultCode'] != 0:
    exit("Error: no session ID")

sessionID = response['sessionID']

if response['currentState']['emission']:
    print("Nicola: " + response['currentState']['emission'])


while len(sessionID) > 0:
    question = input("You: ")

    res = requests.post(
        "https://backend.memori.ai/memori/v2/TextEnteredEvent/" + sessionID,
        json={"text": question},
        headers={"Content-Type": "application/json"}
    )

    response = res.json()

    if response['currentState']['emission']:
        print("Nicola: " + response['currentState']['emission'])
