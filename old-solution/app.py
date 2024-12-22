from flask import Flask, render_template, request, jsonify
import csv

app = Flask(__name__)


#On load
@app.route('/')
def index():
    attendees = []
    try:
        with open('data.csv', mode='r') as file:
            reader = csv.reader(file)
            next(reader)  
            for row in reader:
                attendees.append({
                    'project_name': row[0],
                    'name': row[1],
                    'id': row[2],
                    'present': row[3].lower() == 'true',
                    'late': row[4].lower() == 'true'
                })
    except FileNotFoundError:
        pass  
    return render_template('index.html', attendees=attendees)


#Update attendance
@app.route('/update_attendance', methods=['POST'])
def update_attendance():
    data = request.json
    with open('data.csv', mode='w', newline='') as file:
        writer = csv.writer(file)
        writer.writerow(['project_name', 'name', 'ID', 'present', 'late'])  
        for attendee in data['attendees']:
            writer.writerow([attendee['project_name'], attendee['name'], attendee['id'], 
                             str(attendee['present']), str(attendee['late'])])

    return jsonify({'message': 'Attendance updated successfully'}), 200

if __name__ == "__main__":
    app.run(debug=True, host='192.168.8.70', port=5000)

    

