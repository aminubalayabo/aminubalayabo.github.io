
def load_student_data(filename):
    student_data = {}
    with open(filename, 'r') as file:
        for line in file:
            username, password, *scores = line.strip().split(',')
            student_data[username] = {
                'password': password,
                'scores': list(map(float, scores))
            }
    return student_data

def calculate_scores(scores):
    total = sum(scores)
    average = total / len(scores) if scores else 0
    return total, average

def main():
    filename = 'SOBAS/students_results.txt'
    student_data = load_student_data(filename)

    username = input("Enter your username: ")
    password = input("Enter your password: ")

    if username in student_data and student_data[username]['password'] == password:
        total, average = calculate_scores(student_data[username]['scores'])
        print(f"Total Scores: {total}")
        print(f"Average Score: {average:.2f}")
    else:
        print("Invalid username or password.")

if __name__ == "__main__":
    main()
